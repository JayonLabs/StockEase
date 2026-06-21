<script setup>
import { ref } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';

const page = usePage();

const form = useForm({
    name: '',
    email: '',
    subject: '',
    message: '',
});

function submit() {
    form.post(route('landing.contact.send'), {
        onSuccess: () => form.reset(),
    });
}

const activeFaq = ref(null);
function toggleFaq(i) {
    activeFaq.value = activeFaq.value === i ? null : i;
}

const contactInfo = [
    {
        icon: 'mail',
        title: 'Email Kami',
        lines: ['dewajayon3@gmail.com'],
    },
    {
        icon: 'phone',
        title: 'Hubungi Kami',
        lines: ['Email Kami', 'Sen – Jum, 9 pagi – 6 sore WITA'],
    },
    {
        icon: 'location_on',
        title: 'Kunjungi Kantor Kami',
        lines: ['Email Kami', 'Sen – Jum, 9 pagi – 6 sore WITA'],
    },
];

const faqs = [
    {
        q: 'Seberapa cepat saya bisa mengharapkan respons?',
        a: 'Tim dukungan kami biasanya merespons semua pertanyaan dalam 2 hingga 4 jam kerja. Untuk pelanggan enterprise, dukungan premium menjamin respons dalam 1 jam.',
    },
    {
        q: 'Apakah Anda menawarkan bantuan integrasi teknis?',
        a: 'Ya! Kami menyediakan spesialis integrasi khusus untuk paket Pro dan Enterprise kami untuk membantu Anda memigrasikan data dan menghubungkan StockEase dengan platform POS dan e-commerce Anda yang ada.',
    },
    {
        q: 'Apakah tersedia live chat?',
        a: 'Live chat tersedia untuk pengguna yang sudah login langsung di dalam dasbor StockEase dari jam 9 pagi hingga 6 sore EST, Senin hingga Jumat.',
    },
];
</script>

<template>
    <Head title="Hubungi Kami — Berhubungan dengan Kami" />

    <main class="pt-20">
        <!-- Hero -->
        <section class="relative py-24 overflow-hidden">
            <div class="absolute inset-0 z-0 opacity-10 pointer-events-none">
                <div
                    class="absolute top-[-10%] right-[-10%] w-125 h-125 rounded-full bg-primary-container blur-[120px]"
                ></div>
                <div
                    class="absolute bottom-[-10%] left-[-10%] w-125 h-125 rounded-full bg-secondary-container blur-[120px]"
                ></div>
            </div>
            <div class="max-w-360 mx-auto px-10 relative z-10 text-center">
                <h1
                    class="text-5xl font-bold mb-6 bg-linear-to-br from-surface-tint to-primary-container bg-clip-text text-transparent dark:from-inverse-primary dark:to-primary-fixed"
                >
                    Hubungi Kami
                </h1>
                <p
                    class="text-lg text-on-surface-variant dark:text-surface-variant/80 max-w-2xl mx-auto"
                >
                    Baik Anda memiliki pertanyaan tentang alat manajemen
                    inventaris kami atau membutuhkan solusi ERP kustom, tim kami
                    siap membantu bisnis Anda berkembang dengan presisi.
                </p>
            </div>
        </section>

        <!-- Success flash -->
        <div
            v-if="$page.props.flash?.success"
            class="max-w-360 mx-auto px-10 mb-6"
        >
            <div
                class="bg-secondary-container text-on-secondary-container rounded-xl p-4 flex items-center gap-3"
            >
                <span class="material-symbols-outlined">check_circle</span>
                {{ $page.props.flash.success }}
            </div>
        </div>

        <!-- Bento: form + info -->
        <section class="pb-24 px-10 max-w-360 mx-auto">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                <!-- Contact Form -->
                <div
                    class="lg:col-span-7 glass-card rounded-xl border border-outline-variant/30 p-8 shadow-sm"
                >
                    <h2
                        class="text-2xl font-semibold mb-8 text-on-surface dark:text-inverse-on-surface"
                    >
                        Kirimkan Pesan kepada Kami
                    </h2>
                    <form class="space-y-6" @submit.prevent="submit">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="space-y-2">
                                <Label
                                    for="name"
                                    class="text-on-surface-variant dark:text-surface-variant/80 ml-1"
                                    >Nama Lengkap</Label
                                >
                                <Input
                                    id="name"
                                    v-model="form.name"
                                    type="text"
                                    placeholder="John Doe"
                                    class="h-12 rounded-xl border-outline-variant bg-white/50 dark:bg-white/10 focus:ring-4 focus:ring-surface-tint/20 focus:border-surface-tint"
                                />
                                <p
                                    v-if="form.errors.name"
                                    class="text-xs text-error mt-1"
                                >
                                    {{ form.errors.name }}
                                </p>
                            </div>
                            <div class="space-y-2">
                                <Label
                                    for="email"
                                    class="text-on-surface-variant dark:text-surface-variant/80 ml-1"
                                    >Alamat Email</Label
                                >
                                <Input
                                    id="email"
                                    v-model="form.email"
                                    type="email"
                                    placeholder="john@example.com"
                                    class="h-12 rounded-xl border-outline-variant bg-white/50 dark:bg-white/10 focus:ring-4 focus:ring-surface-tint/20 focus:border-surface-tint"
                                />
                                <p
                                    v-if="form.errors.email"
                                    class="text-xs text-error mt-1"
                                >
                                    {{ form.errors.email }}
                                </p>
                            </div>
                        </div>
                        <div class="space-y-2">
                            <Label
                                for="subject"
                                class="text-on-surface-variant dark:text-surface-variant/80 ml-1"
                                >Subjek</Label
                            >
                            <Input
                                id="subject"
                                v-model="form.subject"
                                type="text"
                                placeholder="Bagaimana kami bisa membantu?"
                                class="h-12 rounded-xl border-outline-variant bg-white/50 dark:bg-white/10 focus:ring-4 focus:ring-surface-tint/20 focus:border-surface-tint"
                            />
                            <p
                                v-if="form.errors.subject"
                                class="text-xs text-error mt-1"
                            >
                                {{ form.errors.subject }}
                            </p>
                        </div>
                        <div class="space-y-2">
                            <Label
                                for="message"
                                class="text-on-surface-variant dark:text-surface-variant/80 ml-1"
                                >Pesan</Label
                            >
                            <textarea
                                id="message"
                                v-model="form.message"
                                rows="5"
                                placeholder="Ceritakan lebih banyak tentang kebutuhan bisnis Anda..."
                                class="w-full px-4 py-3 rounded-xl border border-outline-variant bg-white/50 dark:bg-white/10 focus:ring-4 focus:ring-surface-tint/20 focus:border-surface-tint transition-all outline-none resize-none text-on-surface dark:text-inverse-on-surface text-sm"
                            ></textarea>
                            <p
                                v-if="form.errors.message"
                                class="text-xs text-error mt-1"
                            >
                                {{ form.errors.message }}
                            </p>
                        </div>
                        <Button
                            type="submit"
                            :disabled="form.processing"
                            class="w-full bg-surface-tint hover:bg-surface-tint/90 text-white font-semibold py-4 rounded-xl shadow-md flex items-center justify-center gap-2 transition-all"
                        >
                            <span
                                v-if="!form.processing"
                                class="material-symbols-outlined text-[20px]"
                                >send</span
                            >
                            {{
                                form.processing ? 'Mengirim...' : 'Kirim Pesan'
                            }}
                        </Button>
                    </form>
                </div>

                <!-- Contact Info + Map -->
                <div class="lg:col-span-5 flex flex-col gap-6">
                    <div
                        class="glass-card rounded-xl border border-outline-variant/30 p-8 shadow-sm flex-1"
                    >
                        <h2
                            class="text-2xl font-semibold mb-8 text-on-surface dark:text-inverse-on-surface"
                        >
                            Informasi Kontak
                        </h2>
                        <div class="space-y-8">
                            <div
                                v-for="info in contactInfo"
                                :key="info.title"
                                class="flex items-start gap-4"
                            >
                                <div
                                    class="w-12 h-12 rounded-lg bg-primary-container/20 flex items-center justify-center shrink-0"
                                >
                                    <span
                                        class="material-symbols-outlined text-surface-tint"
                                        >{{ info.icon }}</span
                                    >
                                </div>
                                <div>
                                    <p
                                        class="text-sm font-bold text-on-surface dark:text-inverse-on-surface"
                                    >
                                        {{ info.title }}
                                    </p>
                                    <p
                                        v-for="line in info.lines"
                                        :key="line"
                                        class="text-base text-on-surface-variant dark:text-surface-variant/80"
                                    >
                                        {{ line }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Map -->
                    <div
                        class="h-70 rounded-xl overflow-hidden relative border border-outline-variant/30 shadow-sm group"
                    >
                        <img
                            class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110"
                            src="https://lh3.googleusercontent.com/aida-public/AB6AXuCC-ZOiM9xtJmanhVJqZo3rFHnnG2fl329QxvtbQyXStkTjXnobHS3p5cINuJdF7O58SlLUq9V82i5AjOYLvhCRPTljgap5NPhaxpK5AcH8Dzu4ygB7FhTngIeYwESq8g4gMboham-dtpldtfC34W2giKpH4vKc0aYf9wh2E_5_1-mNgFjFqxSCBIAJASAKuXY3XuSKiOatvHH8okzuGNKYGSVzrEXxnscFjMJjjWLvkeHt8GvgGCG4rW3wHD6_YAuEXxbXZgaPKv0"
                            alt="Lokasi kantor"
                        />
                        <div
                            class="absolute inset-0 bg-surface-tint/10 pointer-events-none"
                        ></div>
                        <div
                            class="absolute bottom-4 left-4 right-4 glass-card p-3 rounded-lg border border-white/20 flex items-center gap-3"
                        >
                            <span
                                class="material-symbols-outlined text-surface-tint"
                                >pin_drop</span
                            >
                            <span
                                class="text-sm font-bold text-on-surface dark:text-inverse-on-surface"
                                >Lihat di Google Maps</span
                            >
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- FAQ -->
        <section
            class="py-24 bg-surface-container-low dark:bg-inverse-surface/50"
        >
            <div class="max-w-4xl mx-auto px-10">
                <h2
                    class="text-3xl font-semibold text-center mb-16 text-on-surface dark:text-inverse-on-surface"
                    style="letter-spacing: -0.01em"
                >
                    Pertanyaan yang Sering Diajukan
                </h2>
                <div class="space-y-4">
                    <div
                        v-for="(faq, i) in faqs"
                        :key="i"
                        class="bg-surface dark:bg-white/10 rounded-xl border border-outline-variant/20 dark:border-outline/20 overflow-hidden"
                    >
                        <button
                            class="w-full p-6 text-left flex justify-between items-center hover:bg-surface-tint/5 dark:hover:bg-white/5 transition-colors"
                            @click="toggleFaq(i)"
                        >
                            <span
                                class="text-2xl font-semibold text-on-surface dark:text-inverse-on-surface"
                                >{{ faq.q }}</span
                            >
                            <span
                                class="material-symbols-outlined transition-transform duration-300"
                                :class="{ 'rotate-180': activeFaq === i }"
                                >expand_more</span
                            >
                        </button>
                        <div
                            v-show="activeFaq === i"
                            class="px-6 pb-6 text-base text-on-surface-variant dark:text-surface-variant/80"
                        >
                            {{ faq.a }}
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA -->
        <section class="py-20 px-10">
            <div
                class="max-w-360 mx-auto bg-inverse-surface rounded-4xl p-12 text-center relative overflow-hidden"
            >
                <div
                    class="absolute top-0 right-0 w-64 h-64 bg-surface-tint/20 rounded-full blur-3xl -translate-y-1/2 translate-x-1/2"
                ></div>
                <div class="relative z-10">
                    <h2
                        class="text-3xl font-semibold text-white mb-4"
                        style="letter-spacing: -0.01em"
                    >
                        Siap mengoptimalkan inventaris Anda?
                    </h2>
                    <p
                        class="text-surface-variant/80 text-lg mb-8 max-w-xl mx-auto"
                    >
                        Bergabunglah dengan ribuan bisnis yang sudah
                        meningkatkan operasional mereka dengan perangkat ERP
                        presisi StockEase.
                    </p>
                    <div class="flex flex-col sm:flex-row gap-4 justify-center">
                        <Link
                            :href="route('login')"
                            class="bg-primary-fixed text-on-primary-fixed font-semibold px-8 py-4 rounded-xl hover:bg-primary-fixed-dim transition-all active:scale-95"
                            >Mulai Uji Coba Gratis</Link
                        >
                        <Link
                            href="#"
                            class="bg-transparent border border-outline text-white font-semibold px-8 py-4 rounded-xl hover:bg-white/5 transition-all active:scale-95"
                            >Jadwalkan Demo</Link
                        >
                    </div>
                </div>
            </div>
        </section>
    </main>
</template>
