<script setup>
import { Button } from '@/Components/ui/button';
import { Eye, RotateCcw } from 'lucide-vue-next';
import { Link } from '@inertiajs/vue3';

import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/Components/ui/tooltip';

const props = defineProps({
    row: { type: Object, required: true },
});
</script>

<template>
    <div class="flex items-center justify-center">
        <TooltipProvider :delay-duration="0">
            <Tooltip>
                <TooltipTrigger>
                    <Link :href="route('sale-return.detail', props.row.id)">
                        <Button
                            aria-label="Lihat detail"
                            variant="ghost"
                            size="icon"
                        >
                            <Eye
                                class="w-4 h-4 text-blue-500 dark:group-hover:text-white"
                            />
                        </Button>
                    </Link>
                </TooltipTrigger>
                <TooltipContent>
                    <p>Detail Retur</p>
                </TooltipContent>
            </Tooltip>
            <Tooltip>
                <TooltipTrigger>
                    <Link :href="route('sale-return.show', props.row.sale_id)">
                        <Button
                            aria-label="Buat retur"
                            variant="ghost"
                            size="icon"
                        >
                            <RotateCcw
                                class="w-4 h-4 text-orange-500 dark:group-hover:text-white"
                            />
                        </Button>
                    </Link>
                </TooltipTrigger>
                <TooltipContent>
                    <p>Buat Retur Baru</p>
                </TooltipContent>
            </Tooltip>
        </TooltipProvider>
    </div>
</template>
