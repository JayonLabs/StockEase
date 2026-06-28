import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

export function useIsLimitHit() {
    return computed(
        () => usePage().props.flash?.error?.includes('Batas maksimal') ?? false,
    );
}
