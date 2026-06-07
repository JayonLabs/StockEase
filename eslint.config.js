import pluginVue from "eslint-plugin-vue";

export default [
    {
        ignores: [
            "vendor/**",
            "node_modules/**",
            "public/**",
            "storage/**",
            "bootstrap/cache/**",
            "resources/js/Components/ui/*",
        ],
    },
    ...pluginVue.configs["flat/recommended"],
    {
        rules: {
            "no-console": ["warn", { allow: ["error"] }],
            "vue/multi-word-component-names": "off",
            "vue/no-use-v-if-with-v-for": "warn",
            "vue/require-default-prop": "off",
        },
    },
];
