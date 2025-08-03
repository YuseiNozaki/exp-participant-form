// JavaScript for the Reservation System

class ReservationApp {
    constructor() {
        this.participantData = null;
        this.selectedSlot = null;
        this.availableSlots = [];
        
        this.init();
    }
    
    init() {
        this.bindEvents();
    }
    
    bindEvents() {
        // Participant form submission
        document.getElementById('participant-form').addEventListener('submit', (e) => {
            e.preventDefault();
            this.handleParticipantRegistration();
        });
        
        // Reservation confirmation
        document.getElementById('confirm-reservation').addEventListener('click', () => {
            this.confirmReservation();
        });
        
        // Cancel selection
        document.getElementById('cancel-selection').addEventListener('click', () => {
            this.cancelSelection();
        });
    }
    
    async handleParticipantRegistration() {
        const form = document.getElementById('participant-form');
        const formData = new FormData(form);
        
        const name = formData.get('name').trim();
        const email = formData.get('email').trim();
        
        if (!name || !email) {
            this.showError('すべての必須項目を入力してください。');
            return;
        }
        
        if (!this.isValidEmail(email)) {
            this.showError('有効なメールアドレスを入力してください。');
            return;
        }
        
        this.participantData = { name, email };
        
        // Show slots section and load available slots
        document.getElementById('registration-section').style.display = 'none';
        document.getElementById('slots-section').style.display = 'block';
        
        await this.loadAvailableSlots();
    }
    
    async loadAvailableSlots() {
        const loadingEl = document.getElementById('slots-loading');
        const containerEl = document.getElementById('slots-container');
        
        loadingEl.style.display = 'block';
        containerEl.innerHTML = '';
        
        try {
            const response = await fetch('/api.php/slots');
            if (!response.ok) {
                throw new Error('Failed to fetch slots');
            }
            
            this.availableSlots = await response.json();
            this.renderSlots();
            
        } catch (error) {
            console.error('Error loading slots:', error);
            this.showError('時間枠の読み込みに失敗しました。ページを更新してもう一度お試しください。');
        } finally {
            loadingEl.style.display = 'none';
        }
    }
    
    renderSlots() {
        const containerEl = document.getElementById('slots-container');
        
        if (this.availableSlots.length === 0) {
            containerEl.innerHTML = '<p class="info-text">現在予約可能な時間枠はありません。</p>';
            return;
        }
        
        // Group slots by date
        const slotsByDate = this.groupSlotsByDate(this.availableSlots);
        
        let html = '';
        for (const [date, slots] of Object.entries(slotsByDate)) {
            html += `
                <div class="date-group">
                    <h3>${this.formatDate(date)}</h3>
                    <table class="slots-table">
                        <thead>
                            <tr>
                                <th>時間</th>
                                <th>状態</th>
                                <th>アクション</th>
                            </tr>
                        </thead>
                        <tbody>
            `;
            
            slots.forEach(slot => {
                const isAvailable = slot.is_available === '1' || slot.is_available === 1;
                html += `
                    <tr>
                        <td>${this.formatTime(slot.start_time)}</td>
                        <td>
                            <span class="${isAvailable ? 'slot-available' : 'slot-unavailable'}">
                                ${isAvailable ? '予約可能' : '予約不可'}
                            </span>
                        </td>
                        <td>
                            <button class="select-slot-btn" 
                                    onclick="app.selectSlot(${slot.id})"
                                    ${!isAvailable ? 'disabled' : ''}>
                                ${isAvailable ? '選択' : '不可'}
                            </button>
                        </td>
                    </tr>
                `;
            });
            
            html += `
                        </tbody>
                    </table>
                </div>
            `;
        }
        
        containerEl.innerHTML = html;
    }
    
    selectSlot(slotId) {
        this.selectedSlot = this.availableSlots.find(slot => slot.id == slotId);
        
        if (!this.selectedSlot) {
            this.showError('選択された時間枠が見つかりません。');
            return;
        }
        
        // Show reservation form
        const formEl = document.getElementById('reservation-form');
        const infoEl = document.getElementById('selected-slot-info');
        
        infoEl.innerHTML = `
            <h4>選択された時間枠</h4>
            <p><strong>日付:</strong> ${this.formatDate(this.selectedSlot.date)}</p>
            <p><strong>時間:</strong> ${this.formatTime(this.selectedSlot.start_time)}</p>
            <p><strong>参加者:</strong> ${this.participantData.name}</p>
            <p><strong>メール:</strong> ${this.participantData.email}</p>
        `;
        
        formEl.style.display = 'block';
        formEl.scrollIntoView({ behavior: 'smooth' });
    }
    
    async confirmReservation() {
        if (!this.selectedSlot || !this.participantData) {
            this.showError('予約に必要な情報が不足しています。');
            return;
        }
        
        const confirmBtn = document.getElementById('confirm-reservation');
        confirmBtn.disabled = true;
        confirmBtn.textContent = '予約中...';
        
        try {
            const response = await fetch('/api.php/reservations', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    slot_id: this.selectedSlot.id,
                    participant_name: this.participantData.name,
                    participant_email: this.participantData.email
                })
            });
            
            const result = await response.json();
            
            if (response.ok && result.success) {
                this.showSuccess();
            } else {
                throw new Error(result.error || '予約に失敗しました');
            }
            
        } catch (error) {
            console.error('Reservation error:', error);
            this.showError('予約の処理中にエラーが発生しました: ' + error.message);
        } finally {
            confirmBtn.disabled = false;
            confirmBtn.textContent = '予約を確定する';
        }
    }
    
    cancelSelection() {
        this.selectedSlot = null;
        document.getElementById('reservation-form').style.display = 'none';
    }
    
    showSuccess() {
        // Hide slots section
        document.getElementById('slots-section').style.display = 'none';
        
        // Show success section
        const successEl = document.getElementById('success-section');
        const detailsEl = document.getElementById('reservation-details');
        
        detailsEl.innerHTML = `
            <div class="reservation-summary">
                <h3>予約詳細</h3>
                <p><strong>お名前:</strong> ${this.participantData.name}</p>
                <p><strong>メールアドレス:</strong> ${this.participantData.email}</p>
                <p><strong>実験日時:</strong> ${this.formatDate(this.selectedSlot.date)} ${this.formatTime(this.selectedSlot.start_time)}</p>
            </div>
        `;
        
        successEl.style.display = 'block';
        successEl.scrollIntoView({ behavior: 'smooth' });
    }
    
    showError(message) {
        // Remove existing error messages
        const existingErrors = document.querySelectorAll('.error-message');
        existingErrors.forEach(el => el.remove());
        
        // Create new error message
        const errorEl = document.createElement('div');
        errorEl.className = 'error-message';
        errorEl.textContent = message;
        
        // Insert at the top of the currently visible section
        const visibleSection = document.querySelector('.section:not([style*="display: none"])');
        if (visibleSection) {
            visibleSection.insertBefore(errorEl, visibleSection.firstChild);
            errorEl.scrollIntoView({ behavior: 'smooth' });
        }
    }
    
    // Utility methods
    groupSlotsByDate(slots) {
        return slots.reduce((groups, slot) => {
            const date = slot.date;
            if (!groups[date]) {
                groups[date] = [];
            }
            groups[date].push(slot);
            return groups;
        }, {});
    }
    
    formatDate(dateString) {
        const date = new Date(dateString);
        const options = { 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric', 
            weekday: 'long' 
        };
        return date.toLocaleDateString('ja-JP', options);
    }
    
    formatTime(timeString) {
        const time = new Date(`2000-01-01T${timeString}`);
        return time.toLocaleTimeString('ja-JP', { 
            hour: '2-digit', 
            minute: '2-digit',
            hour12: false 
        });
    }
    
    isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }
}

// Initialize the application when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.app = new ReservationApp();
});