/**
 * MFW Admin JavaScript
 * Version: 1.0.0
 * Current Time: 2025-05-16 11:13:32
 */

class MFWAdmin {
    constructor() {
        this.currentTime = '2025-05-16 11:13:32';
        this.currentUser = 'maziyarid';
        this.init();
    }

    init() {
        this.initModals();
        this.initBulkGeneration();
        this.initPromptTesting();
        this.initUpdateScheduler();
    }

    initModals() {
        const modals = document.querySelectorAll('.mfw-modal');
        modals.forEach(modal => {
            const close = modal.querySelector('.mfw-modal-close');
            close?.addEventListener('click', () => {
                modal.style.display = 'none';
            });
        });

        window.addEventListener('click', (e) => {
            if (e.target.classList.contains('mfw-modal')) {
                e.target.style.display = 'none';
            }
        });
    }

    initBulkGeneration() {
        const bulkBtn = document.getElementById('mfw-bulk-generate');
        const startBtn = document.getElementById('mfw-start-generation');
        const modal = document.getElementById('mfw-bulk-modal');
        const progressBar = modal?.querySelector('.mfw-progress-bar');
        const progressFill = progressBar?.querySelector('.mfw-progress-fill');
        const currentProgress = modal?.querySelector('.mfw-current');
        const totalProgress = modal?.querySelector('.mfw-total');

        bulkBtn?.addEventListener('click', () => {
            if (modal) modal.style.display = 'block';
        });

        startBtn?.addEventListener('click', async () => {
            const topics = document.getElementById('mfw-topics')?.value.split('\n').filter(t => t.trim());
            if (!topics?.length) {
                alert('Please enter at least one topic');
                return;
            }

            const settings = this.getGenerationSettings();
            const progress = modal?.querySelector('.mfw-generation-progress');
            if (progress) progress.style.display = 'block';
            
            if (totalProgress) totalProgress.textContent = topics.length;

            let completed = 0;
            for (const topic of topics) {
                try {
                    await this.generateContent(topic, settings);
                    completed++;
                    if (currentProgress) currentProgress.textContent = completed;
                    if (progressFill) {
                        progressFill.style.width = `${(completed / topics.length) * 100}%`;
                    }
                } catch (error) {
                    console.error(`Failed to generate content for "${topic}":`, error);
                }
            }

            alert('Bulk generation completed!');
            if (modal) modal.style.display = 'none';
        });
    }

    async generateContent(topic, settings) {
        const response = await fetch('/wp-json/mfw/v1/generate', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': mfwData.nonce
            },
            body: JSON.stringify({
                topic,
                ...settings
            })
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        return await response.json();
    }

    getGenerationSettings() {
        return {
            type: document.getElementById('mfw-content-type')?.value || 'post',
            tone: document.getElementById('mfw-tone')?.value || 'professional',
            length: document.getElementById('mfw-length')?.value || 'medium',
            seo_target: document.getElementById('mfw-seo-target')?.value || 'yoast',
            generate_images: document.getElementById('mfw-generate-images')?.checked || false,
            image_style: document.getElementById('mfw-image-style')?.value || 'realistic',
            image_count: parseInt(document.getElementById('mfw-image-count')?.value || '3', 10)
        };
    }

    initPromptTesting() {
        const testButtons = document.querySelectorAll('.mfw-test-prompt');
        testButtons.forEach(button => {
            button.addEventListener('click', async () => {
                const promptId = button.dataset.promptId;
                const promptText = document.querySelector(`#prompt-${promptId}`)?.value;
                
                if (!promptText) return;

                button.disabled = true;
                try {
                    const result = await this.testPrompt(promptText);
                    document.querySelector(`#result-${promptId}`)?.textContent = result.content;
                } catch (error) {
                    console.error('Prompt test failed:', error);
                    alert('Failed to test prompt. Please try again.');
                } finally {
                    button.disabled = false;
                }
            });
        });
    }

    async testPrompt(prompt) {
        const response = await fetch('/wp-json/mfw/v1/test-prompt', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': mfwData.nonce
            },
            body: JSON.stringify({ prompt })
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        return await response.json();
    }

    initUpdateScheduler() {
        const scheduleType = document.getElementById('mfw-update-schedule');
        const customInterval = document.getElementById('mfw-custom-interval');
        
        scheduleType?.addEventListener('change', (e) => {
            if (customInterval) {
                customInterval.style.display = e.target.value === 'custom' ? 'block' : 'none';
            }
        });

        // Initialize update conditions
        const conditions = document.querySelectorAll('.mfw-update-condition');
        conditions.forEach(condition => {
            condition.addEventListener('change', () => {
                this.saveUpdateSettings();
            });
        });
    }

    async saveUpdateSettings() {
        const settings = {
            schedule: document.getElementById('mfw-update-schedule')?.value,
            custom_interval: parseInt(document.getElementById('mfw-custom-interval')?.value || '30', 10),
            conditions: {
                traffic_threshold: parseInt(document.getElementById('mfw-traffic-threshold')?.value || '1000', 10),
                keyword_changes: document.getElementById('mfw-keyword-changes')?.checked || false,
                competitors_update: document.getElementById('mfw-competitors-update')?.checked || false
            }
        };

        try {
            const response = await fetch('/wp-json/mfw/v1/settings/update', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': mfwData.nonce
                },
                body: JSON.stringify(settings)
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const result = await response.json();
            if (result.success) {
                this.showNotice('Settings saved successfully', 'success');
            }
        } catch (error) {
            console.error('Failed to save settings:', error);
            this.showNotice('Failed to save settings', 'error');
        }
    }

    showNotice(message, type = 'info') {
        const notice = document.createElement('div');
        notice.className = `notice notice-${type} is-dismissible`;
        notice.innerHTML = `<p>${message}</p>`;
        
        const wrapper = document.querySelector('.wrap');
        if (wrapper) {
            wrapper.insertBefore(notice, wrapper.firstChild);
        }

        setTimeout(() => {
            notice.remove();
        }, 3000);
    }
}

// Initialize admin functionality
document.addEventListener('DOMContentLoaded', () => {
    window.mfwAdmin = new MFWAdmin();
});