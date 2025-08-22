
import { showConferenceDetails, registerToConference, toggleFavorite } from '../controllers/conferenceHandlers.js';
import { mockConferences } from '../controllers/conferenceControllers.js';

export function displayConferences(conferences) {
    const container = document.getElementById('conferences-list');
    if (!container) return;

    // Si no hay conferencias, mostrar estado vacío
    if (!conferences || conferences.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <i class="fas fa-search"></i>
                <h3>No se encontraron conferencias</h3>
                <p>Intenta ajustar los filtros de búsqueda</p>
            </div>
        `;
        return;
    }

    container.innerHTML = conferences.map(conference => `
        <div class="conference-card" data-id="${conference.id}">
            <div class="card-header">
                <img src="${conference.image}" alt="${conference.name}">
                <div class="card-date">${formatDate(conference.date)}</div>
                <div class="card-favorite ${isFavorite(conference.id) ? 'active' : ''}" data-id="${conference.id}">
                    <i class="fas fa-heart"></i>
                </div>
            </div>
            <div class="card-content">
                <h3 class="card-title">${conference.name}</h3>
                <div class="card-speaker">
                    <img src="${conference.speakerImage}" alt="${conference.speaker}">
                    <span>${conference.speaker}</span>
                </div>
                <div class="card-details">
                    <div class="card-detail">
                        <i class="fas fa-clock"></i>
                        <span>${conference.time} - ${conference.duration}</span>
                    </div>
                    <div class="card-detail">
                        <i class="fas fa-map-marker-alt"></i>
                        <span>${conference.location}</span>
                    </div>
                    <div class="card-detail">
                        <i class="fas fa-users"></i>
                        <span>${conference.registered} / ${conference.capacity} inscritos</span>
                    </div>
                </div>
                <div class="card-tags">
                    ${conference.tags.map(tag => `<span class="card-tag">${tag}</span>`).join('')}
                </div>
                <p>${conference.description}</p>
                <div class="card-actions">
                    <button class="btn btn-outline view-details" data-id="${conference.id}">
                        <i class="fas fa-eye"></i> Ver detalles
                    </button>
                    <button class="btn btn-primary register-btn" data-id="${conference.id}">
                        <i class="fas fa-user-plus"></i> Inscribirse
                    </button>
                </div>
            </div>
        </div>
    `).join('');

    // Añadir event listeners a los botones
    document.querySelectorAll('.view-details').forEach(btn => {
        btn.addEventListener('click', (e) => {
            const id = parseInt(e.target.closest('.view-details').dataset.id);
            showConferenceDetails(id, conferences);
        });
    });

    document.querySelectorAll('.register-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            const id = parseInt(e.target.closest('.register-btn').dataset.id);
            registerToConference(id, conferences);
        });
    });

    document.querySelectorAll('.card-favorite').forEach(btn => {
        btn.addEventListener('click', (e) => {
            const id = parseInt(e.target.closest('.card-favorite').dataset.id);
            toggleFavorite(id);
        });
    });
}

// Función auxiliar para verificar si una conferencia es favorita
function isFavorite(id) {
    const favorites = JSON.parse(localStorage.getItem('favorites')) || [];
    return favorites.includes(id);
}

// Función auxiliar para formatear fechas
function formatDate(dateString) {
    const options = { day: 'numeric', month: 'short', year: 'numeric' };
    return new Date(dateString).toLocaleDateString('es-ES', options);
}