// Project Creation Wizard JavaScript
class ProjectWizard {
    constructor() {
        this.currentStep = 1;
        this.totalSteps = 3;
        this.projectData = {};
        this.templates = [];
        
        this.init();
    }
    
    async init() {
        this.setupEventListeners();
        await this.loadTemplates();
        this.showStep(1);
    }
    
    setupEventListeners() {
        // Navigation buttons
        document.getElementById('nextBtn')?.addEventListener('click', () => this.nextStep());
        document.getElementById('prevBtn')?.addEventListener('click', () => this.prevStep());
        document.getElementById('cancelBtn')?.addEventListener('click', () => this.closeWizard());
        
        // Template selection
        document.addEventListener('click', (e) => {
            if (e.target.closest('.template-card')) {
                this.selectTemplate(e.target.closest('.template-card'));
            }
        });
        
        // Form inputs
        document.getElementById('projectName')?.addEventListener('input', (e) => {
            this.validateProjectName(e.target.value);
        });
        
        // Import methods
        document.getElementById('importFiles')?.addEventListener('change', (e) => {
            this.handleFileImport(e.target.files);
        });
        
        document.getElementById('importUrl')?.addEventListener('input', (e) => {
            this.validateImportUrl(e.target.value);
        });
        
        // Keyboard navigation
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.closeWizard();
            } else if (e.key === 'Enter' && !e.shiftKey) {
                if (this.currentStep < this.totalSteps) {
                    e.preventDefault();
                    this.nextStep();
                }
            }
        });
    }
    
    async loadTemplates() {
        try {
            const response = await fetch('project-templates-complete.php?action=get_templates');
            const data = await response.json();
            
            if (data.success) {
                this.templates = data.templates;
                this.renderTemplates();
            }
        } catch (error) {
            console.error('Failed to load templates:', error);
        }
    }
    
    renderTemplates() {
        const container = document.getElementById('templatesGrid');
        if (!container) return;
        
        container.innerHTML = '';
        
        this.templates.forEach(template => {
            const card = document.createElement('div');
            card.className = 'template-card';
            card.dataset.templateId = template.id;
            
            card.innerHTML = `
                <div class="template-icon">
                    <i class="fas ${template.icon}"></i>
                </div>
                <div class="template-info">
                    <h3>${template.name}</h3>
                    <p>${template.description}</p>
                    <span class="template-category">${template.category}</span>
                </div>
                <div class="template-files">
                    <i class="fas fa-file"></i>
                    <span>${Object.keys(template.files).length} files</span>
                </div>
            `;
            
            container.appendChild(card);
        });
    }
    
    selectTemplate(card) {
        // Remove previous selection
        document.querySelectorAll('.template-card').forEach(c => {
            c.classList.remove('selected');
        });
        
        // Select new template
        card.classList.add('selected');
        this.projectData.templateId = card.dataset.templateId;
        
        // Enable next button
        const nextBtn = document.getElementById('nextBtn');
        if (nextBtn) {
            nextBtn.disabled = false;
        }
    }
    
    validateProjectName(name) {
        const errorElement = document.getElementById('nameError');
        const nextBtn = document.getElementById('nextBtn');
        
        if (name.length < 3) {
            if (errorElement) {
                errorElement.textContent = 'Project name must be at least 3 characters';
                errorElement.style.display = 'block';
            }
            if (nextBtn) nextBtn.disabled = true;
            return false;
        }
        
        if (!/^[a-zA-Z0-9\s_-]+$/.test(name)) {
            if (errorElement) {
                errorElement.textContent = 'Project name can only contain letters, numbers, spaces, underscores, and hyphens';
                errorElement.style.display = 'block';
            }
            if (nextBtn) nextBtn.disabled = true;
            return false;
        }
        
        if (errorElement) {
            errorElement.style.display = 'none';
        }
        if (nextBtn) nextBtn.disabled = false;
        
        this.projectData.name = name.trim();
        return true;
    }
    
    validateImportUrl(url) {
        const errorElement = document.getElementById('urlError');
        const nextBtn = document.getElementById('nextBtn');
        
        if (!url) {
            if (errorElement) errorElement.style.display = 'none';
            if (nextBtn) nextBtn.disabled = true;
            return false;
        }
        
        try {
            new URL(url);
            if (errorElement) errorElement.style.display = 'none';
            if (nextBtn) nextBtn.disabled = false;
            this.projectData.importUrl = url;
            return true;
        } catch (e) {
            if (errorElement) {
                errorElement.textContent = 'Please enter a valid URL';
                errorElement.style.display = 'block';
            }
            if (nextBtn) nextBtn.disabled = true;
            return false;
        }
    }
    
    handleFileImport(files) {
        const fileList = document.getElementById('fileList');
        const nextBtn = document.getElementById('nextBtn');
        
        if (!fileList) return;
        
        fileList.innerHTML = '';
        
        const validFiles = Array.from(files).filter(file => {
            const validTypes = ['text/html', 'text/css', 'application/javascript', 'application/json', 'text/markdown'];
            const validExtensions = ['.html', '.css', '.js', '.json', '.md'];
            
            return validTypes.includes(file.type) || 
                   validExtensions.some(ext => file.name.toLowerCase().endsWith(ext));
        });
        
        if (validFiles.length === 0) {
            fileList.innerHTML = '<p class="error">No valid files selected. Please select HTML, CSS, JS, JSON, or Markdown files.</p>';
            if (nextBtn) nextBtn.disabled = true;
            return;
        }
        
        validFiles.forEach(file => {
            const fileItem = document.createElement('div');
            fileItem.className = 'file-item';
            fileItem.innerHTML = `
                <i class="fas fa-file"></i>
                <span>${file.name}</span>
                <span class="file-size">${this.formatFileSize(file.size)}</span>
            `;
            fileList.appendChild(fileItem);
        });
        
        this.projectData.importFiles = validFiles;
        if (nextBtn) nextBtn.disabled = false;
    }
    
    formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
    
    showStep(step) {
        // Hide all steps
        document.querySelectorAll('.wizard-step').forEach(stepElement => {
            stepElement.style.display = 'none';
        });
        
        // Show current step
        const currentStepElement = document.getElementById(`step${step}`);
        if (currentStepElement) {
            currentStepElement.style.display = 'block';
        }
        
        // Update progress
        this.updateProgress();
        
        // Update navigation buttons
        this.updateNavigation();
        
        // Focus first input in current step
        setTimeout(() => {
            const firstInput = currentStepElement?.querySelector('input, textarea, select');
            if (firstInput) {
                firstInput.focus();
            }
        }, 100);
    }
    
    updateProgress() {
        const progressFill = document.getElementById('progressFill');
        const progressText = document.getElementById('progressText');
        
        if (progressFill) {
            progressFill.style.width = `${(this.currentStep / this.totalSteps) * 100}%`;
        }
        
        if (progressText) {
            progressText.textContent = `Step ${this.currentStep} of ${this.totalSteps}`;
        }
    }
    
    updateNavigation() {
        const prevBtn = document.getElementById('prevBtn');
        const nextBtn = document.getElementById('nextBtn');
        const finishBtn = document.getElementById('finishBtn');
        
        if (prevBtn) {
            prevBtn.style.display = this.currentStep === 1 ? 'none' : 'inline-flex';
        }
        
        if (nextBtn) {
            nextBtn.style.display = this.currentStep === this.totalSteps ? 'none' : 'inline-flex';
        }
        
        if (finishBtn) {
            finishBtn.style.display = this.currentStep === this.totalSteps ? 'inline-flex' : 'none';
        }
    }
    
    nextStep() {
        if (this.currentStep < this.totalSteps) {
            this.currentStep++;
            this.showStep(this.currentStep);
        }
    }
    
    prevStep() {
        if (this.currentStep > 1) {
            this.currentStep--;
            this.showStep(this.currentStep);
        }
    }
    
    async finishWizard() {
        const finishBtn = document.getElementById('finishBtn');
        const originalText = finishBtn?.textContent;
        
        if (finishBtn) {
            finishBtn.disabled = true;
            finishBtn.textContent = 'Creating...';
        }
        
        try {
            const result = await this.createProject();
            
            if (result.success) {
                this.showSuccess(result.project_id);
            } else {
                this.showError(result.error || 'Failed to create project');
            }
        } catch (error) {
            this.showError('An unexpected error occurred');
            console.error('Project creation error:', error);
        } finally {
            if (finishBtn) {
                finishBtn.disabled = false;
                finishBtn.textContent = originalText;
            }
        }
    }
    
    async createProject() {
        const formData = new FormData();
        
        // Common project data
        formData.append('action', 'import_sample');
        formData.append('project_name', this.projectData.name);
        formData.append('project_description', this.projectData.description || '');
        formData.append('framework', this.projectData.framework || 'vanilla');
        
        // Determine creation method
        const creationMethod = this.projectData.method || 'template';
        
        if (creationMethod === 'template') {
            formData.append('template_id', this.projectData.templateId || 'blank');
        } else if (creationMethod === 'files') {
            // Switch to file import action
            formData.set('action', 'import_files');
            
            // Add files
            if (this.projectData.importFiles) {
                for (const file of this.projectData.importFiles) {
                    formData.append('project_files[]', file);
                }
            }
        } else if (creationMethod === 'url') {
            // Switch to URL import action
            formData.set('action', 'import_url');
            formData.append('import_url', this.projectData.importUrl);
        }
        
        const response = await fetch('project-import.php', {
            method: 'POST',
            body: formData
        });
        
        return await response.json();
    }
    
    showSuccess(projectId) {
        const wizardContent = document.querySelector('.wizard-content');
        if (wizardContent) {
            wizardContent.innerHTML = `
                <div class="success-message">
                    <div class="success-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h2>Project Created Successfully!</h2>
                    <p>Your project has been created and is ready to edit.</p>
                    <div class="success-actions">
                        <button class="btn btn-primary" onclick="window.location.href='dashboard.php?page=workspace&project_id=${projectId}'">>
                            <i class="fas fa-code"></i>
                            Open in Editor
                        </button>
                        <button class="btn btn-secondary" onclick="window.location.href='projects.php'">
                            <i class="fas fa-folder"></i>
                            View All Projects
                        </button>
                    </div>
                </div>
            `;
        }
    }
    
    showError(message) {
        const errorElement = document.getElementById('wizardError');
        if (errorElement) {
            errorElement.textContent = message;
            errorElement.style.display = 'block';
        }
        
        setTimeout(() => {
            if (errorElement) {
                errorElement.style.display = 'none';
            }
        }, 5000);
    }
    
    closeWizard() {
        if (confirm('Are you sure you want to cancel? Any unsaved changes will be lost.')) {
            window.location.href = 'projects.php';
        }
    }
}

// Initialize wizard when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    // Check if we're on the projects page
    if (document.getElementById('projectWizard')) {
        new ProjectWizard();
    }
    
    // Open wizard from create button
    const createButtons = document.querySelectorAll('[data-action="create-project"]');
    createButtons.forEach(button => {
        button.addEventListener('click', () => {
            const wizard = document.getElementById('projectWizard');
            if (wizard) {
                wizard.style.display = 'flex';
                new ProjectWizard();
            }
        });
    });
});
