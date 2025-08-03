// Admin Dashboard JavaScript

class AdminApp {
    constructor() {
        this.isLoggedIn = false;
        this.slots = [];
        this.reservations = [];
        
        this.init();
    }
    
    init() {
        this.bindEvents();
        this.checkAuthStatus();
    }
    
    bindEvents() {
        // Login form
        document.getElementById('login-form').addEventListener('submit', (e) => {
            e.preventDefault();
            this.handleLogin();
        });
        
        // Logout button
        document.getElementById('logout-btn').addEventListener('click', () => {
            this.handleLogout();
        });
    }
    
    checkAuthStatus() {
        // Check if user is already logged in (using sessionStorage)
        const isLoggedIn = sessionStorage.getItem('admin_logged_in');
        if (isLoggedIn === 'true') {
            this.showDashboard();
        }
    }
    
    async handleLogin() {
        const username = document.getElementById('username').value;
        const password = document.getElementById('password').value;
        
        // Simple authentication (in production, this should be server-side)
        if (username === 'admin' && password === 'admin123') {
            sessionStorage.setItem('admin_logged_in', 'true');
            this.showDashboard();
        } else {
            this.showError('ユーザー名またはパスワードが正しくありません。');
        }
    }
    
    handleLogout() {
        sessionStorage.removeItem('admin_logged_in');
        document.getElementById('login-section').style.display = 'block';
        document.getElementById('admin-dashboard').style.display = 'none';
        this.isLoggedIn = false;
    }
    
    showDashboard() {
        document.getElementById('login-section').style.display = 'none';
        document.getElementById('admin-dashboard').style.display = 'block';
        this.isLoggedIn = true;
        
        this.loadDashboardData();
    }
    
    async loadDashboardData() {
        await Promise.all([
            this.loadSlots(),
            this.loadReservations()
        ]);
        
        this.updateStatistics();
    }
    
    async loadSlots() {
        try {
            const response = await fetch('/api.php/slots/all');
            if (!response.ok) throw new Error('Failed to fetch slots');
            
            this.slots = await response.json();
            this.renderSlotsManagement();
            
        } catch (error) {
            console.error('Error loading slots:', error);
            this.showError('時間枠データの読み込みに失敗しました。');
        } finally {
            document.getElementById('slots-loading').style.display = 'none';
        }
    }
    
    async loadReservations() {
        try {
            const response = await fetch('/api.php/reservations');
            if (!response.ok) throw new Error('Failed to fetch reservations');
            
            this.reservations = await response.json();
            this.renderReservationsManagement();
            
        } catch (error) {
            console.error('Error loading reservations:', error);
            this.showError('予約データの読み込みに失敗しました。');
        } finally {
            document.getElementById('reservations-loading').style.display = 'none';
        }
    }
    
    renderSlotsManagement() {
        const container = document.getElementById('slots-management');
        
        if (this.slots.length === 0) {
            container.innerHTML = '<p class="info-text">時間枠がありません。</p>';
            return;
        }
        
        // Group slots by date
        const slotsByDate = this.groupSlotsByDate(this.slots);
        
        let html = '';
        for (const [date, slots] of Object.entries(slotsByDate)) {
            html += `
                <div class="date-group">
                    <h3>${this.formatDate(date)}</h3>
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>時間</th>
                                <th>公開状態</th>
                                <th>予約数</th>
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
                            <label class="toggle-switch">
                                <input type="checkbox" ${isAvailable ? 'checked' : ''} 
                                       onchange="admin.toggleSlotAvailability(${slot.id})">
                                <span class="toggle-slider"></span>
                            </label>
                            <span class="status-badge ${isAvailable ? 'status-available' : 'status-unavailable'}">
                                ${isAvailable ? '公開' : '非公開'}
                            </span>
                        </td>
                        <td>
                            <span class="reservation-count">${slot.reservation_count || 0}</span>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn btn-info btn-sm" onclick="admin.viewSlotDetails(${slot.id})">
                                    詳細
                                </button>
                            </div>
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
        
        container.innerHTML = html;
    }
    
    renderReservationsManagement() {
        const container = document.getElementById('reservations-management');
        
        if (this.reservations.length === 0) {
            container.innerHTML = '<p class="info-text">予約がありません。</p>';
            return;
        }
        
        let html = `
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>日付</th>
                        <th>時間</th>
                        <th>参加者名</th>
                        <th>メールアドレス</th>
                        <th>予約日時</th>
                        <th>状態</th>
                        <th>アクション</th>
                    </tr>
                </thead>
                <tbody>
        `;
        
        this.reservations.forEach(reservation => {
            html += `
                <tr>
                    <td>${this.formatDate(reservation.date)}</td>
                    <td>${this.formatTime(reservation.start_time)}</td>
                    <td>${reservation.name}</td>
                    <td>${reservation.email}</td>
                    <td>${this.formatDateTime(reservation.reserved_at)}</td>
                    <td>
                        <span class="status-badge status-${reservation.status}">
                            ${reservation.status === 'active' ? 'アクティブ' : 'キャンセル'}
                        </span>
                    </td>
                    <td>
                        <div class="action-buttons">
                            ${reservation.status === 'active' ? 
                                `<button class="btn btn-danger btn-sm" onclick="admin.cancelReservation(${reservation.id})">
                                    キャンセル
                                </button>` : 
                                '<span class="text-muted">-</span>'
                            }
                        </div>
                    </td>
                </tr>
            `;
        });
        
        html += `
                </tbody>
            </table>
        `;
        
        container.innerHTML = html;
    }
    
    async toggleSlotAvailability(slotId) {
        try {
            const response = await fetch(`/api.php/slots/${slotId}/toggle`, {
                method: 'PUT'
            });
            
            if (!response.ok) throw new Error('Failed to toggle slot availability');
            
            // Reload slots to reflect changes
            await this.loadSlots();
            this.updateStatistics();
            
        } catch (error) {
            console.error('Error toggling slot availability:', error);
            this.showError('時間枠の公開状態の変更に失敗しました。');
        }
    }
    
    async cancelReservation(reservationId) {
        if (!confirm('この予約をキャンセルしますか？')) {
            return;
        }
        
        try {
            const response = await fetch(`/api.php/reservations/${reservationId}/cancel`, {
                method: 'PUT'
            });
            
            if (!response.ok) throw new Error('Failed to cancel reservation');
            
            // Reload data to reflect changes
            await this.loadDashboardData();
            
        } catch (error) {
            console.error('Error cancelling reservation:', error);
            this.showError('予約のキャンセルに失敗しました。');
        }
    }
    
    viewSlotDetails(slotId) {
        const slot = this.slots.find(s => s.id == slotId);
        if (!slot) return;
        
        const slotReservations = this.reservations.filter(r => r.slot_id == slotId);
        
        let details = `
            時間枠詳細\n
            日付: ${this.formatDate(slot.date)}\n
            時間: ${this.formatTime(slot.start_time)}\n
            公開状態: ${slot.is_available ? '公開' : '非公開'}\n
            予約数: ${slot.reservation_count || 0}\n
        `;
        
        if (slotReservations.length > 0) {
            details += '\n予約者:\n';
            slotReservations.forEach(r => {
                details += `- ${r.name} (${r.email})\n`;
            });
        }
        
        alert(details);
    }
    
    updateStatistics() {
        const totalSlots = this.slots.length;
        const availableSlots = this.slots.filter(s => s.is_available).length;
        const totalReservations = this.reservations.filter(r => r.status === 'active').length;
        const uniqueParticipants = new Set(this.reservations.map(r => r.email)).size;
        
        document.getElementById('total-slots').textContent = totalSlots;
        document.getElementById('available-slots').textContent = availableSlots;
        document.getElementById('total-reservations').textContent = totalReservations;
        document.getElementById('total-participants').textContent = uniqueParticipants;
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
    
    formatDateTime(datetimeString) {
        const date = new Date(datetimeString);
        return date.toLocaleString('ja-JP');
    }
    
    showError(message) {
        // Remove existing error messages
        const existingErrors = document.querySelectorAll('.error-message');
        existingErrors.forEach(el => el.remove());
        
        // Create new error message
        const errorEl = document.createElement('div');
        errorEl.className = 'error-message';
        errorEl.textContent = message;
        
        // Insert at the top of the dashboard
        const dashboard = document.getElementById('admin-dashboard');
        if (dashboard.style.display !== 'none') {
            dashboard.insertBefore(errorEl, dashboard.firstChild);
        } else {
            const loginSection = document.getElementById('login-section');
            loginSection.insertBefore(errorEl, loginSection.firstChild);
        }
    }
}

// Initialize the admin application when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.admin = new AdminApp();
});