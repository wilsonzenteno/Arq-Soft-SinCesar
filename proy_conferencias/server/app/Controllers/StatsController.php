<?php
declare(strict_types=1);

namespace App\Controllers;

class StatsController
{
    /* ===================== Rutas públicas ===================== */

    public function talk(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) { $this->error(400, 'id requerido'); return; }
        $this->json($this->statsFor('talk', $id));
    }

    public function course(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) { $this->error(400, 'id requerido'); return; }
        $this->json($this->statsFor('course', $id));
    }

    public function webinar(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) { $this->error(400, 'id requerido'); return; }
        $this->json($this->statsFor('webinar', $id));
    }

    /* ===================== Núcleo de estadísticas ===================== */

    private function statsFor(string $kind, int $id): array
    {
        // Mapeo de tablas/PK por tipo
        $map = [
            'talk'    => ['reg' => 'talk_registrations',    'vote' => 'talk_votes',    'eval' => 'talk_evaluations',    'pk' => 'talk_id'],
            'course'  => ['reg' => 'course_registrations',  'vote' => 'course_votes',  'eval' => 'course_evaluations',  'pk' => 'course_id'],
            'webinar' => ['reg' => 'webinar_registrations', 'vote' => 'webinar_votes', 'eval' => 'webinar_evaluations', 'pk' => 'webinar_id'],
        ];
        if (!isset($map[$kind])) return ['error' => 'tipo inválido'];
        $t  = $map[$kind];
        $pk = $t['pk'];

        // 1) Inscritos (conteo exacto por Content-Range)
        $registrations = $this->pgrestCount($t['reg'], [$pk => "eq.$id"]);

        // 2) Likes / Dislikes
        $likes    = $this->pgrestCount($t['vote'], [$pk => "eq.$id", 'liked' => 'eq.true']);
        $dislikes = $this->pgrestCount($t['vote'], [$pk => "eq.$id", 'liked' => 'eq.false']);

        // 3) Evaluaciones: count + avg por cada pregunta (alias con sintaxis correcta de PostgREST)
        // select=q1:avg(q1_useful),q2:avg(q2_expectations),q3:avg(q3_content),q4:avg(q4_logistics),count
        $evalAgg = $this->pgrestSelectOne(
            $t['eval'],
            [$pk => "eq.$id"],
            'q1:avg(q1_useful),q2:avg(q2_expectations),q3:avg(q3_content),q4:avg(q4_logistics),count'
        );

        return [
            'registrations' => $registrations,
            'likes'         => $likes,
            'dislikes'      => $dislikes,
            'evaluations'   => [
                'count' => (int)($evalAgg['count'] ?? 0),
                'avg'   => [
                    'q1_useful'       => $this->flt($evalAgg['q1'] ?? null),
                    'q2_expectations' => $this->flt($evalAgg['q2'] ?? null),
                    'q3_content'      => $this->flt($evalAgg['q3'] ?? null),
                    'q4_logistics'    => $this->flt($evalAgg['q4'] ?? null),
                ]
            ]
        ];
    }

    /* ===================== Utilidades PostgREST ===================== */

    private function pgrestHeaders(): array
    {
        $cfg = require __DIR__ . '/../../config.php';
        return [
            'Content-Type: application/json',
            'apikey: ' . ($cfg['SUPABASE_SERVICE_ROLE'] ?? ''),
            'Authorization: Bearer ' . ($cfg['SUPABASE_SERVICE_ROLE'] ?? ''),
            'Prefer: count=exact'
        ];
    }

    private function pgrestBase(): string
    {
        $cfg = require __DIR__ . '/../../config.php';
        $url = rtrim((string)($cfg['SUPABASE_URL'] ?? ''), '/');
        return $url . '/rest/v1/';
    }

    /** Conteo exacto mediante Content-Range */
    private function pgrestCount(string $table, array $filters): int
    {
        $qs = [];
        foreach ($filters as $k => $v) {
            $qs[] = rawurlencode($k) . '=' . rawurlencode($v);
        }
        $url = $this->pgrestBase() . $table . '?' . implode('&', $qs);

        // Hacemos GET con limit=1 y leemos el header Content-Range (más portable que HEAD)
        $ch = curl_init($url . '&select=id&limit=1');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_NOBODY         => false,
            CURLOPT_HTTPHEADER     => $this->pgrestHeaders(),
        ]);
        $resp        = curl_exec($ch);
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $headers_str = substr($resp ?: '', 0, $header_size);
        curl_close($ch);

        if (preg_match('/Content-Range:\s*\d+-\d+\/(\d+)/i', $headers_str, $m)) {
            return (int)$m[1];
        }
        return 0;
    }

    /** select con agregaciones; devuelve la primera fila o arreglo vacío */
    private function pgrestSelectOne(string $table, array $filters, string $select): array
    {
        $qs = ['select=' . rawurlencode($select)];
        foreach ($filters as $k => $v) {
            $qs[] = rawurlencode($k) . '=' . rawurlencode($v);
        }
        $url = $this->pgrestBase() . $table . '?' . implode('&', $qs) . '&limit=1';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $this->pgrestHeaders(),
        ]);
        $body = curl_exec($ch);
        curl_close($ch);

        $arr = json_decode($body ?: '[]', true);
        return $arr[0] ?? ['count' => 0];
    }

    private function flt($v)
    {
        return is_null($v) ? null : round((float)$v, 2);
    }

    /* ===================== Helpers de respuesta ===================== */

    private function json($data): void
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data);
    }

    private function error(int $code, string $msg): void
    {
        http_response_code($code);
        $this->json(['error' => $msg]);
    }
}
