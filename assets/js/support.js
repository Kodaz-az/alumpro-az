// Support system JavaScript

class SupportSystem {
    constructor() {
        this.chatContainer = null;
        this.messageInput = null;
        this.sessionId = this.generateSessionId();
        this.isRecording = false;
        this.recognition = null;
        this.salesPersonConnected = false;
        
        this.init();
    }

    init() {
        this.chatContainer = document.getElementById('supportChat');
        this.messageInput = document.getElementById('supportMessage');
        
        if (!this.chatContainer || !this.messageInput) return;

        this.setupEventListeners();
        this.initSpeechRecognition();
        this.loadQuickQuestions();
        this.identifyUser();
    }

    setupEventListeners() {
        // Send button
        const sendBtn = document.getElementById('sendSupportMessage');
        if (sendBtn) {
            sendBtn.addEventListener('click', () => this.sendMessage());
        }

        // Enter key
        this.messageInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.sendMessage();
            }
        });

        // Voice button
        const voiceBtn = document.getElementById('voiceButton');
        if (voiceBtn) {
            voiceBtn.addEventListener('click', () => this.toggleVoiceRecording());
        }

        // Quick questions
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('quick-question')) {
                e.preventDefault();
                const question = e.target.getAttribute('data-question');
                if (question) {
                    this.messageInput.value = question;
                    this.sendMessage();
                }
            }
        });
    }

    initSpeechRecognition() {
        if ('webkitSpeechRecognition' in window || 'SpeechRecognition' in window) {
            const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
            this.recognition = new SpeechRecognition();
            
            this.recognition.continuous = false;
            this.recognition.interimResults = false;
            this.recognition.lang = 'az-AZ';

            this.recognition.onstart = () => {
                this.isRecording = true;
                this.updateVoiceButton();
            };

            this.recognition.onend = () => {
                this.isRecording = false;
                this.updateVoiceButton();
            };

            this.recognition.onresult = (event) => {
                const transcript = event.results[0][0].transcript;
                this.messageInput.value = transcript;
                this.sendMessage();
            };

            this.recognition.onerror = (event) => {
                console.error('Speech recognition error:', event.error);
                this.isRecording = false;
                this.updateVoiceButton();
            };
        } else {
            // Hide voice button if not supported
            const voiceBtn = document.getElementById('voiceButton');
            if (voiceBtn) {
                voiceBtn.style.display = 'none';
            }
        }
    }

    toggleVoiceRecording() {
        if (!this.recognition) return;

        if (this.isRecording) {
            this.recognition.stop();
        } else {
            this.recognition.start();
        }
    }

    updateVoiceButton() {
        const voiceBtn = document.getElementById('voiceButton');
        if (!voiceBtn) return;

        const icon = voiceBtn.querySelector('i');
        if (this.isRecording) {
            icon.className = 'bi bi-mic-fill text-danger';
            voiceBtn.classList.add('btn-outline-danger');
            voiceBtn.classList.remove('btn-outline-secondary');
            voiceBtn.title = 'Dayandir';
        } else {
            icon.className = 'bi bi-mic';
            voiceBtn.classList.add('btn-outline-secondary');
            voiceBtn.classList.remove('btn-outline-danger');
            voiceBtn.title = 'Səs yazma';
        }
    }

    loadQuickQuestions() {
        fetch('../api/support.php?action=get_quick_questions')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.renderQuickQuestions(data.questions);
                }
            })
            .catch(error => {
                console.error('Quick questions error:', error);
            });
    }

    renderQuickQuestions(questions) {
        const container = document.getElementById('quickQuestions');
        if (!container) return;

        const html = questions.map(q => 
            `<a href="#" class="quick-question" data-question="${q.keyword}">${q.keyword}</a>`
        ).join('');

        container.innerHTML = html;
    }

    identifyUser() {
        // Get user info from session/cookie
        fetch('../api/support.php?action=identify_user')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.user) {
                    this.addMessage(`Salam ${data.user.name}! Sizə necə kömək edə bilərəm?`, false, true);
                }
            })
            .catch(error => {
                console.error('User identification error:', error);
            });
    }

    sendMessage() {
        const message = this.messageInput.value.trim();
        if (!message) return;

        // Add user message to chat
        this.addMessage(message, true);
        
        // Clear input
        this.messageInput.value = '';

        // Send to server
        this.processMessage(message);
    }

    processMessage(message) {
        const formData = new FormData();
        formData.append('action', 'send_message');
        formData.append('message', message);
        formData.append('session_id', this.sessionId);

        fetch('../api/support.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (data.sales_person_connected) {
                    this.salesPersonConnected = true;
                    this.addMessage('Satış menecerimiz qoşuldu. İndi birbaşa söhbət edə bilərsiniz.', false, true);
                }
                
                if (data.response) {
                    this.addMessage(data.response, false);
                }
            } else {
                this.addMessage('Xəta baş verdi. Zəhmət olmasa yenidən cəhd edin.', false);
            }
        })
        .catch(error => {
            console.error('Support message error:', error);
            this.addMessage('Əlaqə xətası. Zəhmət olmasa yenidən cəhd edin.', false);
        });
    }

    addMessage(message, isUser = false, isSystem = false) {
        const messageDiv = document.createElement('div');
        messageDiv.className = `chat-message ${isUser ? 'user' : 'bot'}`;
        
        const bubbleClass = isUser ? 'user' : 'bot';
        const icon = isSystem ? '<i class="bi bi-robot"></i> ' : '';
        
        messageDiv.innerHTML = `
            <div class="chat-bubble ${bubbleClass}">
                ${icon}${message}
            </div>
            <small class="text-muted">${this.getCurrentTime()}</small>
        `;

        this.chatContainer.appendChild(messageDiv);
        this.scrollToBottom();
    }

    scrollToBottom() {
        this.chatContainer.scrollTop = this.chatContainer.scrollHeight;
    }

    getCurrentTime() {
        return new Date().toLocaleTimeString('az-AZ', { 
            hour: '2-digit', 
            minute: '2-digit' 
        });
    }

    generateSessionId() {
        return 'support_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    }
}

// Initialize support system when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    new SupportSystem();
});