/**
 * Bloxer Dashboard - JavaScript Fixes
 * Fixes for modals, navigation, and broken functions
 */

// Modal Management System
const ModalManager = {
    showModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'flex';
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';
            
            // Auto-focus first input
            setTimeout(() => {
                const firstInput = modal.querySelector('input, textarea, select');
                if (firstInput) firstInput.focus();
            }, 100);
        }
    },
    
    hideModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'none';
            modal.classList.remove('show');
            document.body.style.overflow = '';
        }
    },
    
    closeAllModals() {
        document.querySelectorAll('[id$="-modal"], .studio-modal').forEach(modal => {
            modal.style.display = 'none';
            modal.classList.remove('show');
        });
        document.body.style.overflow = '';
    }
};

// Show modals
function showCreateProjectModal() {
    ModalManager.showModal('create-project-modal');
}

function closeModal() {
    ModalManager.hideModal('create-project-modal');
}

function showPublishModal(projectId) {
    const modal = document.getElementById('publish-modal');
    const projectIdInput = document.getElementById('publish-project-id');
    
    if (projectIdInput) {
        projectIdInput.value = projectId;
    }
    
    ModalManager.showModal('publish-modal');
}

function closePublishModal() {
    ModalManager.hideModal('publish-modal');
    const form = document.getElementById('publish-form');
    if (form) form.reset();
}

function openChatModal(offerId, buyerName) {
    const modal = document.getElementById('chat-modal');
    const title = document.getElementById('chat-modal-title');
    const offerIdInput = document.getElementById('chat-offer-id');
    
    if (title) title.textContent = 'Chat with ' + buyerName;
    if (offerIdInput) offerIdInput.value = offerId;
    
    loadChatMessages(offerId);
    ModalManager.showModal('chat-modal');
}

function closeChatModal() {
    ModalManager.hideModal('chat-modal');
    const form = document.getElementById('chat-form');
    if (form) form.reset();
}

// Close modal on escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        ModalManager.closeAllModals();
    }
});

// Close modal when clicking outside
document.addEventListener('click', function(e) {
    if (e.target.classList && e.target.classList.contains('studio-modal')) {
        ModalManager.hideModal(e.target.id);
    }
    if (e.target.classList && e.target.classList.contains('modal')) {
        ModalManager.hideModal(e.target.id);
    }
});

// Navigation
function navigate(page, projectId = null) {
    const currentFile = window.location.pathname.split('/').pop() || 'dashboard.php';
    const currentPath = window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/'));
    
    let url = currentFile + '?page=' + page;
    if (projectId) {
        url += '&project_id=' + projectId;
    }
    
    window.location.href = url;
}

// Sidebar toggle
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    if (sidebar) {
        sidebar.classList.toggle('collapsed');
        localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
    }
}

// Load sidebar state on page load
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    if (sidebar && localStorage.getItem('sidebarCollapsed') === 'true') {
        sidebar.classList.add('collapsed');
    }
});

// Project management
function deleteProject(event, projectId) {
    event.stopPropagation();
    
    if (confirm('Are you sure? This action cannot be undone.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_project">
            <input type="hidden" name="project_id" value="${projectId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Form validation
function toggleProjectType() {
    const projectType = document.getElementById('project_type');
    const uploadSection = document.getElementById('upload-section');
    const folderSection = document.getElementById('folder-section');
    
    if (!projectType) return;
    
    const value = projectType.value;
    
    if (uploadSection) uploadSection.style.display = value === 'upload' ? 'block' : 'none';
    if (folderSection) folderSection.style.display = value === 'folder' ? 'block' : 'none';
}

function validateFolderUpload() {
    const projectType = document.getElementById('project_type');
    const folderInput = document.getElementById('project_folder');
    
    if (!projectType || projectType.value !== 'folder') return true;
    
    if (!folderInput || !folderInput.files || folderInput.files.length === 0) {
        alert('Please select a folder to upload.');
        return false;
    }
    
    if (folderInput.files.length > 100) {
        alert('Maximum 100 files allowed.');
        return false;
    }
    
    return true;
}

function validatePublishForm() {
    const title = document.getElementById('app-title');
    const shortDesc = document.getElementById('app-short-description');
    const description = document.getElementById('app-description');
    const category = document.getElementById('app-category');
    
    if (!title || !title.value.trim() || title.value.trim().length < 3) {
        alert('App title must be at least 3 characters.');
        return false;
    }
    
    if (!shortDesc || shortDesc.value.trim().length > 500) {
        alert('Short description must not exceed 500 characters.');
        return false;
    }
    
    if (!description || description.value.trim().length < 10) {
        alert('Full description must be at least 10 characters.');
        return false;
    }
    
    if (!category || !category.value) {
        alert('Please select a category.');
        return false;
    }
    
    return true;
}

// Chat functions
function loadChatMessages(offerId) {
    const messagesContainer = document.getElementById('chat-messages');
    if (!messagesContainer) return;
    
    fetch('dashboard.php?action=get_chat_messages&offer_id=' + offerId)
        .then(async response => {
            if (!response.headers.get("content-type")?.includes("application/json")) {
                const text = await response.text();
                console.error("Backend zwrócił nie-JSON:", text);
                throw new Error("Invalid JSON from server");
            }
            return response.json();
        })
        .then(data => {
            if (data && data.success) {
                displayChatMessages(data.messages);
            }
        })
        .catch(error => {
            console.error('Error loading messages:', error);
            messagesContainer.innerHTML = '<div style="color: red; padding: 20px; text-align: center;">Failed to load messages</div>';
        });
}

function displayChatMessages(messages) {
    const container = document.getElementById('chat-messages');
    if (!container) return;
    
    container.innerHTML = '';
    
    if (!messages || messages.length === 0) {
        container.innerHTML = '<div style="text-align: center; color: #6b7280; padding: 20px;">No messages yet.</div>';
        return;
    }
    
    messages.forEach(msg => {
        const msgDiv = document.createElement('div');
        msgDiv.className = 'chat-message';
        msgDiv.style.alignSelf = msg.is_sender ? 'flex-end' : 'flex-start';
        msgDiv.style.background = msg.is_sender ? '#6366f1' : '#f3f4f6';
        msgDiv.style.color = msg.is_sender ? 'white' : '#1f2937';
        msgDiv.innerHTML = `
            <div style="font-size: 0.75rem; opacity: 0.7; margin-bottom: 4px;">
                ${msg.username} · ${new Date(msg.created_at).toLocaleString()}
            </div>
            <div>${msg.message}</div>
        `;
        container.appendChild(msgDiv);
    });
    
    container.scrollTop = container.scrollHeight;
}

// Chat form submission
const chatForm = document.getElementById('chat-form');
if (chatForm) {
    chatForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(chatForm);
        formData.append('action', 'send_chat_message');
        
        fetch('dashboard.php', {
            method: 'POST',
            body: formData
        })
        .then(async response => {
            if (!response.headers.get("content-type")?.includes("application/json")) {
                const text = await response.text();
                console.error("Backend zwrócił nie-JSON:", text);
                throw new Error("Invalid JSON from server");
            }
            return response.json();
        })
        .then(data => {
            if (data && data.success) {
                document.getElementById('chat-message').value = '';
                const offerId = document.getElementById('chat-offer-id').value;
                loadChatMessages(offerId);
            } else {
                alert('Error: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to send message.');
        });
    });
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Attach keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Ctrl/Cmd + N for new project
        if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
            e.preventDefault();
            showCreateProjectModal();
        }
    });
    
    // Fix form submissions
    const forms = document.querySelectorAll('.studio-form-stack[onsubmit]');
    forms.forEach(form => {
        if (form.hasAttribute('onsubmit')) {
            const onsubmit = form.getAttribute('onsubmit');
            form.removeAttribute('onsubmit');
            form.addEventListener('submit', function(e) {
                if (onsubmit.includes('validateFolderUpload')) {
                    if (!validateFolderUpload()) e.preventDefault();
                } else if (onsubmit.includes('validatePublishForm')) {
                    if (!validatePublishForm()) e.preventDefault();
                }
            });
        }
    });
});

// Export functions for use in HTML
window.ModalManager = ModalManager;
window.showCreateProjectModal = showCreateProjectModal;
window.closeModal = closeModal;
window.showPublishModal = showPublishModal;
window.closePublishModal = closePublishModal;
window.openChatModal = openChatModal;
window.closeChatModal = closeChatModal;
window.navigate = navigate;
window.toggleSidebar = toggleSidebar;
window.deleteProject = deleteProject;
window.toggleProjectType = toggleProjectType;
window.validateFolderUpload = validateFolderUpload;
window.validatePublishForm = validatePublishForm;
