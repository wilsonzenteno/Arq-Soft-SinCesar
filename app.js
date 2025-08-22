// RUTAS CORRECTAS desde la raíz (donde está app.js)
import { fetchConferences, filterConferences } from './js/controllers/conferenceControllers.js';
import { closeModal, showNotification } from './js/controllers/conferenceHandlers.js';

// Estado de la aplicación
let darkMode = localStorage.getItem('darkMode') === 'true';

// Inicializar la aplicación
function init() {
    fetchConferences();
    setupEventListeners();
    updateTheme();
}

// Configurar event listeners
function setupEventListeners() {
    // Búsqueda y filtros
    document.getElementById('search-input').addEventListener('input', filterConferences);
    document.getElementById('category-filter').addEventListener('change', filterConferences);
    document.getElementById('date-filter').addEventListener('change', filterConferences);
    
    // Tema oscuro/claro
    document.getElementById('theme-toggle').addEventListener('click', toggleTheme);
    
    // Cerrar modal
    document.getElementById('modal-close').addEventListener('click', closeModal);
    
    // Cerrar modal al hacer clic fuera del contenido
    document.getElementById('conference-modal').addEventListener('click', (e) => {
        if (e.target === document.getElementById('conference-modal')) closeModal();
    });
    
    // Botón de nueva conferencia
    document.getElementById('add-conference').addEventListener('click', () => {
        showNotification('Funcionalidad en desarrollo', 'success');
    });
}

// Alternar tema claro/oscuro
function toggleTheme() {
    darkMode = !darkMode;
    localStorage.setItem('darkMode', darkMode);
    updateTheme();
}

// Actualizar tema
function updateTheme() {
    if (darkMode) {
        document.body.classList.add('dark-mode');
        document.getElementById('theme-toggle').innerHTML = '<i class="fas fa-sun"></i> Modo Claro';
    } else {
        document.body.classList.remove('dark-mode');
        document.getElementById('theme-toggle').innerHTML = '<i class="fas fa-moon"></i> Modo Oscuro';
    }
}

// Inicializar la aplicación cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', init);