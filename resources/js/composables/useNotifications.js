import { ref, computed, watch } from 'vue';
import axios from 'axios';
import { usePage } from '@inertiajs/vue3';
import { formatRelative } from '@/lib/utils';

/** Module-level state — persists across component remounts (Inertia page navigations). */
const notifications = ref([]);
const initialized = ref(false);
let echoSubscribed = false;

export function resetNotifications() {
    notifications.value = [];
    initialized.value = false;
    echoSubscribed = false;
}

export function useNotifications() {
    const page = usePage();

    const unreadCount = computed(
        () => notifications.value.filter((n) => !n.read_at).length,
    );

    watch(
        () => page.props.auth?.user,
        (newUser) => {
            if (!newUser) {
                resetNotifications();
            }
        },
    );

    const fetchNotifications = async () => {
        try {
            const response = await axios.get(route('notifications.index'));
            notifications.value = response.data.data.map((notif) => ({
                id: notif.id,
                slug: notif.data.product_slug,
                product_id: notif.data.product_id,
                message: notif.data.message,
                product_name: notif.data.product_name,
                current_stock: notif.data.current_stock,
                alert_level: notif.data.alert_level,
                read_at: notif.read_at,
                created_at: notif.created_at,
                time_ago: formatRelative(notif.created_at),
            }));
        } catch (_error) {
            console.error('Failed to fetch notifications');
        }
    };

    const subscribeEcho = () => {
        if (echoSubscribed || !window.Echo || !page.props.auth?.user?.id) {
            return;
        }

        window.Echo.private(
            `App.Models.User.${page.props.auth.user.id}`,
        ).notification((notification) => {
            if (notification.type === 'stock.alert') {
                notifications.value.unshift({
                    id: notification.id,
                    slug: notification.product_slug,
                    product_id: notification.product_id,
                    message: notification.message,
                    product_name: notification.product_name,
                    current_stock: notification.current_stock,
                    alert_level: notification.alert_level,
                    read_at: null,
                    created_at: notification.created_at,
                    time_ago: formatRelative(notification.created_at),
                });
            }
        });

        echoSubscribed = true;
    };

    const initialize = async () => {
        if (!initialized.value) {
            await fetchNotifications();
            initialized.value = true;
        }

        subscribeEcho();
    };

    const markAsRead = async (notificationId) => {
        try {
            await axios.post(
                route('notifications.read', { id: notificationId }),
            );
            notifications.value = notifications.value.map((n) =>
                n.id === notificationId
                    ? { ...n, read_at: new Date().toISOString() }
                    : n,
            );
        } catch (_error) {
            console.error('Failed to mark notification as read');
        }
    };

    const markAllAsRead = async () => {
        try {
            await axios.post(route('notifications.read-all'));
            notifications.value = notifications.value.map((n) => ({
                ...n,
                read_at: new Date().toISOString(),
            }));
        } catch (_error) {
            console.error('Failed to mark all notifications as read');
        }
    };

    const deleteNotification = async (notificationId) => {
        try {
            await axios.delete(
                route('notifications.destroy', { id: notificationId }),
            );
            notifications.value = notifications.value.filter(
                (n) => n.id !== notificationId,
            );
        } catch (_error) {
            console.error('Failed to delete notification');
        }
    };

    return {
        notifications,
        unreadCount,
        initialize,
        markAsRead,
        markAllAsRead,
        deleteNotification,
        resetNotifications,
    };
}
