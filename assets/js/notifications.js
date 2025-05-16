/**
 * MFW Notifications Handler
 * Version: 1.0.0
 * Current Time: 2025-05-16 10:42:20
 */
class MFWNotifications {
    constructor() {
        this.currentTime = '2025-05-16 10:42:20';
        this.container = document.querySelector('.mfw-notifications-panel');
        this.notificationsList = this.container?.querySelector('.mfw-notifications-list');
        this.filter = this.container?.querySelector('.mfw-filter-notifications');
        this.markAllReadBtn = this.container?.querySelector('.mfw-mark-all-read');
        this.page = 1;
        this.perPage = 10;
        this.loading = false;

        this.init();
    }

    init() {
        if (!this.container) return;

        // Initialize event listeners
        this.initializeEventListeners();

        // Initialize infinite scroll
        this.initializeInfiniteScroll();

        // Initialize real-time updates
        this.initializeRealTimeUpdates();
    }

    initializeEventListeners() {
        // Filter change handler
        this.filter?.addEventListener('change', (e) => {
            this.page = 1;
            this.loadNotifications(e.target.value);
        });

        // Mark all as read handler
        this.markAllReadBtn?.addEventListener('click', () => {
            this.markAllAsRead();
        });

        // Individual notification actions
        this.notificationsList?.addEventListener('click', (e) => {
            const notificationItem = e.target.closest('.mfw-notification-item');
            if (!notificationItem) return;

            const notificationId = notificationItem.dataset.id;

            if (e.target.closest('.mfw-mark-read')) {
                this.markAsRead(notificationId);
            } else if (e.target.closest('.mfw-delete')) {
                this.deleteNotification(notificationId);
            }
        });
    }

    initializeInfiniteScroll() {
        const options = {
            root: null,
            rootMargin: '0px',
            threshold: 0.1
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting && !this.loading) {
                    this.loadMore();
                }
            });
        }, options);

        // Observe the last notification item
        const updateObserver = () => {
            const items = this.notificationsList?.children;
            if (items?.length) {
                observer.observe(items[items.length - 1]);
            }
        };

        // Initial observation
        updateObserver();

        // Store the update function for later use
        this.updateInfiniteScroll = updateObserver;
    }

    initializeRealTimeUpdates() {
        if (typeof WebSocket === 'undefined') return;

        const wsProtocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
        const wsUrl = `${wsProtocol}//${window.location.host}/wp-json/mfw/v1/notifications/ws`;

        this.ws = new WebSocket(wsUrl);

        this.ws.addEventListener('message', (event) => {
            try {
                const data = JSON.parse(event.data);
                if (data.type === 'notification') {
                    this.handleRealTimeNotification(data.notification);
                }
            } catch (error) {
                console.error('Failed to parse WebSocket message:', error);
            }
        });

        this.ws.addEventListener('close', () => {
            // Attempt to reconnect after 5 seconds
            setTimeout(() => this.initializeRealTimeUpdates(), 5000);
        });
    }

    async loadNotifications(filter = 'all') {
        this.loading = true;
        this.showLoader();

        try {
            const response = await fetch(`/wp-json/mfw/v1/notifications?page=${this.page}&per_page=${this.perPage}&filter=${filter}`);
            const data = await response.json();

            if (this.page === 1) {
                this.notificationsList.innerHTML = '';
            }

            this.renderNotifications(data.notifications);
            this.updatePagination(data.total, data.total_pages);

        } catch (error) {
            console.error('Failed to load notifications:', error);
            this.showError('Failed to load notifications');
        } finally {
            this.loading = false;
            this.hideLoader();
            this.updateInfiniteScroll();
        }
    }

    async markAsRead(notificationId) {
        try {
            const response = await fetch(`/wp-json/mfw/v1/notifications/${notificationId}/read`, {
                method: 'POST',
                headers: {
                    'X-WP-Nonce': window.mfwData.nonce
                }
            });

            if (response.ok) {
                const notificationItem = this.notificationsList?.querySelector(`[data-id="${notificationId}"]`);
                notificationItem?.classList.remove('unread');
                this.updateUnreadCount();
            }
        } catch (error) {
            console.error('Failed to mark notification as read:', error);
        }
    }