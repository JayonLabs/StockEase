import { ref } from 'vue';

const isDark = ref(false);

export function useLandingDarkMode() {
    function toggle() {
        isDark.value = !isDark.value;
        document.documentElement.classList.toggle('dark', isDark.value);
    }

    return { isDark, toggle };
}
