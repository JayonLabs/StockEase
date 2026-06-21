import { clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';
import dayjs from 'dayjs';
import 'dayjs/locale/id';
import relativeTime from 'dayjs/plugin/relativeTime';

export function cn(...inputs) {
    return twMerge(clsx(inputs));
}

export function valueUpdater(updaterOrValue, ref) {
    ref.value =
        typeof updaterOrValue === 'function'
            ? updaterOrValue(ref.value)
            : updaterOrValue;
}

export const formatPrice = (price) =>
    new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
    }).format(price);

export const formatNumber = (number) =>
    new Intl.NumberFormat('id-ID').format(number);

dayjs.locale('id');
dayjs.extend(relativeTime);

// Fungsi format created at lengkap
export function formatDateTime(datetime) {
    return dayjs(datetime).format('DD MMM YYYY HH:mm'); // 17 Jul 2025 21:04
}

export function formatDate(datetime) {
    return dayjs(datetime).format('DD MMM YYYY'); // 17 Jul 2025
}

export function formatTime(datetime) {
    return dayjs(datetime).format('HH:mm'); // 21:04
}

// ⏳ Format relative time → contoh: "2 hari yang lalu"
export function formatRelative(datetime) {
    return dayjs(datetime).fromNow();
}

export function getCurrentUrlQuery(exclude = []) {
    const params = Object.fromEntries(
        new URLSearchParams(window.location.search),
    );
    exclude.forEach((key) => {
        delete params[key];
    });
    return params;
}

export function filterMenuByRole(
    menu,
    role,
    permissions = [],
    planFeatures = {},
) {
    return menu
        .filter((item) => {
            if (
                item.permissions &&
                item.permissions.some((p) => permissions.includes(p))
            ) {
                return true;
            }

            if (item.roles && item.roles.includes(role)) {
                return true;
            }

            return false;
        })
        .map((item) => ({
            ...item,
            locked:
                item.planFeature !== undefined
                    ? planFeatures[item.planFeature] === false
                    : false,
        }));
}
