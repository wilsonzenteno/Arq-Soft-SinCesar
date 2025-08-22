import { displayConferences } from '../views/conferenceView.js';

// --- DATOS DE PRUEBA ---
export const mockConferences = [
    {
        id: 1,
        name: "Conferencia de Tecnología Web 2025",
        description: "Un evento para explorar las últimas tendencias en desarrollo web y JavaScript.",
        speaker: "Dra. María López",
        speakerImage: "https://randomuser.me/api/portraits/women/32.jpg",
        date: "2025-09-15",
        time: "10:00",
        duration: "2 horas",
        category: "tecnologia",
        tags: ["Web", "JavaScript", "Tecnología"],
        image: "https://images.unsplash.com/photo-1581094794329-c8112a89af12?ixlib=rb-4.0.3&auto=format&fit=crop&w=600&q=80",
        location: "Auditorio Principal, La Paz",
        capacity: 200,
        registered: 145
    },
    {
        id: 2,
        name: "Curso Intensivo de Arquitectura de Software",
        description: "Aprende los patrones y principios para construir sistemas robustos y escalables.",
        speaker: "Lic. Carlos Méndez",
        speakerImage: "https://randomuser.me/api/portraits/men/22.jpg",
        date: "2025-09-20",
        time: "15:30",
        duration: "3 horas",
        category: "tecnologia",
        tags: ["Arquitectura", "Software", "Patrones"],
        image: "https://images.unsplash.com/photo-1517245386807-bb43f82c33c4?ixlib=rb-4.0.3&auto=format&fit=crop&w=600&q=80",
        location: "Centro de Convenciones, Santa Cruz",
        capacity: 150,
        registered: 132
    },
    {
        id: 3,
        name: "Webinar: Introducción a Supabase",
        description: "Descubre cómo construir un backend completo en un fin de semana.",
        speaker: "Ing. Jorge Silva",
        speakerImage: "https://randomuser.me/api/portraits/men/45.jpg",
        date: "2025-09-25",
        time: "09:00",
        duration: "4 horas",
        category: "tecnologia",
        tags: ["Supabase", "Backend", "Base de datos"],
        image: "https://images.unsplash.com/photo-1633356122544-f134324a6cee?ixlib=rb-4.0.3&auto=format&fit=crop&w=600&q=80",
        location: "Aula Magna, Cochabamba",
        capacity: 120,
        registered: 98
    }
];

// La función para obtener conferencias
export function fetchConferences() {
    try {
        displayConferences(mockConferences);
    } catch (error) {
        console.error('Error al mostrar las conferencias:', error.message);
    }
}

// Función para filtrar conferencias
export function filterConferences() {
    const searchTerm = document.getElementById('search-input').value.toLowerCase();
    const category = document.getElementById('category-filter').value;
    const date = document.getElementById('date-filter').value;

    let filtered = mockConferences.filter(conference => {
        // Filtrar por término de búsqueda
        const matchesSearch = conference.name.toLowerCase().includes(searchTerm) || 
                            conference.description.toLowerCase().includes(searchTerm) ||
                            conference.speaker.toLowerCase().includes(searchTerm) ||
                            conference.tags.some(tag => tag.toLowerCase().includes(searchTerm));
        
        // Filtrar por categoría
        const matchesCategory = category === '' || conference.category === category;
        
        // Filtrar por fecha
        let matchesDate = true;
        if (date !== '') {
            const conferenceDate = new Date(conference.date);
            const today = new Date();
            
            switch(date) {
                case 'today':
                    matchesDate = isSameDay(conferenceDate, today);
                    break;
                case 'week':
                    const weekStart = new Date(today);
                    weekStart.setDate(today.getDate() - today.getDay());
                    const weekEnd = new Date(weekStart);
                    weekEnd.setDate(weekStart.getDate() + 6);
                    matchesDate = conferenceDate >= weekStart && conferenceDate <= weekEnd;
                    break;
                case 'month':
                    matchesDate = conferenceDate.getMonth() === today.getMonth() && 
                                 conferenceDate.getFullYear() === today.getFullYear();
                    break;
            }
        }
        
        return matchesSearch && matchesCategory && matchesDate;
    });

    displayConferences(filtered);
}

// Función auxiliar: comprobar si es el mismo día
function isSameDay(date1, date2) {
    return date1.getDate() === date2.getDate() &&
           date1.getMonth() === date2.getMonth() &&
           date1.getFullYear() === date2.getFullYear();
}