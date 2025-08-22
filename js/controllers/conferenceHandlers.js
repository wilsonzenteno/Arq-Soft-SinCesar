import { displayConferences } from '../views/conferenceView.js';
import { mockConferences } from './conferenceControllers.js';

// Mostrar detalles de la conferencia
export function showConferenceDetails(id, conferences = mockConferences) {
    const conference = conferences.find(c => c.id === id);
    if (!conference) return;

    const modal = document.getElementById('conference-modal');
    const modalContent = document.getElementById('modal-content');
    const favorites = JSON.parse(localStorage.getItem('favorites')) || [];

    modalContent.innerHTML = `
        <div class="card-header">
            <img src="${conference.image}" alt="${conference.name}">
            <div class="card-date">${formatDate(conference.date)}</div>
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
                <div class="card-detail">
                    <i class="fas fa-tag"></i>
                    <span>${capitalizeFirstLetter(conference.category)}</span>
                </div>
            </div>
            <div class="card-tags">
                ${conference.tags.map(tag => `<span class="card-tag">${tag}</span>`).join('')}
            </div>
            <p>${conference.description}</p>
            <div class="card-actions" style="margin-top: 20px;">
                <button class="btn btn-outline" id="modal-favorite" data-id="${conference.id}">
                    <i class="fas fa-heart"></i> ${favorites.includes(conference.id) ? 'Quitar de favoritos' : 'Añadir a favoritos'}
                </button>
                <button class="btn btn-primary" id="modal-register" data-id="${conference.id}">
                    <i class="fas fa-user-plus"></i> Inscribirse
                </button>
            </div>
        </div>
    `;

    // Añadir event listeners a los botones del modal
    document.getElementById('modal-favorite').addEventListener('click', () => {
        toggleFavorite(conference.id);
        closeModal();
    });

    document.getElementById('modal-register').addEventListener('click', () => {
        registerToConference(conference.id);
        closeModal();
    });

    modal.style.display = 'flex';
}

// Cerrar modal
export function closeModal() {
    const modal = document.getElementById('conference-modal');
    modal.style.display = 'none';
}

// Inscribirse a una conferencia
export function registerToConference(id, conferences = mockConferences) {
    const conference = conferences.find(c => c.id === id);
    if (!conference) return;
    
    if (conference.registered < conference.capacity) {
        conference.registered++;
        showNotification('Te has inscrito correctamente a la conferencia', 'success');
        displayConferences(conferences);
    } else {
        showNotification('Lo sentimos, esta conferencia ya está llena', 'error');
    }
}

// Alternar favorito
export function toggleFavorite(id) {
    let favorites = JSON.parse(localStorage.getItem('favorites')) || [];
    const index = favorites.indexOf(id);
    
    if (index === -1) {
        favorites.push(id);
        showNotification('Conferencia añadida a favoritos', 'success');
    } else {
        favorites.splice(index, 1);
        showNotification('Conferencia eliminada de favoritos', 'success');
    }
    
    localStorage.setItem('favorites', JSON.stringify(favorites));
    displayConferences(mockConferences);
}

// Mostrar notificación
export function showNotification(message, type) {
    const notification = document.getElementById('notification');
    const notificationText = document.getElementById('notification-text');
    
    notification.className = `notification ${type}`;
    notificationText.textContent = message;
    notification.classList.add('show');
    
    setTimeout(() => {
        notification.classList.remove('show');
    }, 3000);
}

// Función auxiliar: formatear fecha
function formatDate(dateString) {
    const options = { day: 'numeric', month: 'short', year: 'numeric' };
    return new Date(dateString).toLocaleDateString('es-ES', options);
}

// Función auxiliar: capitalizar primera letra
function capitalizeFirstLetter(string) {
    return string.charAt(0).toUpperCase() + string.slice(1);
}