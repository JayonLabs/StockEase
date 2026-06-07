<script setup>
import { Badge } from '@/Components/ui/badge';
import FilePdfIcon from '@/Components/FilePdfIcon.vue';
import FileExcelIcon from '@/Components/FileExcelIcon.vue';
import axios from 'axios';
import { toast } from 'vue-sonner';
import { Download, EllipsisVertical, Loader2, Trash2 } from 'lucide-vue-next';
import { Card, CardContent } from '@/Components/ui/card';
import { ref } from 'vue';
import { Button } from '@/Components/ui/button';

import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';

import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/Components/ui/tooltip';

import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/Components/ui/alert-dialog';

const props = defineProps({
    file: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(['delete', 'download']);

const isLoading = ref(false);
const isDialogOpen = ref(false);

const handleDownload = (filePath) => {
    window.open(route('file-manager.download', { file: filePath }), '_blank');
};

const handleDelete = async (filePath) => {
    isLoading.value = true;

    await axios
        .delete(route('file-manager.destroy'), {
            data: { file: filePath },
        })
        .then((response) => {
            emit('delete');
            toast.success(response.data.message);
            isDialogOpen.value = false;
        })
        .catch((_error) => {
            toast.error('File gagal dihapus');
        })
        .finally(() => {
            isLoading.value = false;
        });
};
</script>

<template>
    <Card
        class="group overflow-hidden transition-all hover:shadow-md hover:border-primary/50 relative bg-card h-full"
    >
        <!-- Opsi Menu Pojok Kanan Atas -->
        <div class="absolute top-2 right-2 z-10">
            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <Button
                        aria-label="Menu file"
                        variant="ghost"
                        size="icon"
                        class="h-8 w-8 text-muted-foreground hover:text-foreground hover:bg-muted/80 focus-visible:ring-0 focus-visible:ring-offset-0"
                    >
                        <EllipsisVertical class="w-4 h-4" />
                        <span class="sr-only">Open menu</span>
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" class="w-40">
                    <DropdownMenuItem
                        class="cursor-pointer"
                        @click="handleDownload(file.path)"
                    >
                        <Download class="w-4 h-4 mr-2" />
                        Download
                    </DropdownMenuItem>

                    <DropdownMenuItem
                        class="cursor-pointer text-destructive focus:bg-destructive focus:text-destructive-foreground"
                        @click="isDialogOpen = true"
                    >
                        <Trash2 class="w-4 h-4 mr-2" />
                        Hapus
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </div>

        <CardContent
            class="p-4 flex flex-col items-center justify-start h-full text-center pt-8 gap-3"
        >
            <!-- Icon Besar di Tengah -->
            <div
                class="shrink-0 rounded-2xl p-4 bg-muted/30 group-hover:bg-primary/5 transition-colors flex items-center justify-center"
            >
                <FileExcelIcon
                    v-if="file.file_extension == 'xlsx'"
                    class="w-16 h-16"
                />
                <FilePdfIcon
                    v-if="file.file_extension == 'pdf'"
                    class="w-16 h-16"
                />
            </div>

            <!-- Detail File (Nama, Ekstensi, Ukuran, Tanggal) -->
            <div class="flex flex-col items-center w-full min-w-0">
                <TooltipProvider>
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <h3
                                class="truncate w-full text-sm font-medium leading-snug group-hover:text-primary transition-colors cursor-default px-1"
                            >
                                {{ file.name }}
                            </h3>
                        </TooltipTrigger>
                        <TooltipContent
                            side="bottom"
                            class="max-w-[300px] break-words text-center"
                        >
                            <p>{{ file.name }}</p>
                        </TooltipContent>
                    </Tooltip>
                </TooltipProvider>

                <div
                    class="flex flex-col items-center justify-center text-[11px] text-muted-foreground font-medium w-full mt-2 gap-0.5"
                >
                    <Badge
                        variant="outline"
                        class="uppercase text-[9px] font-bold px-2 py-0 mb-1 border-muted-foreground/30"
                    >
                        {{ file.file_extension }}
                    </Badge>
                    <span>{{ file.size }}</span>
                    <span>{{ file.last_modified }}</span>
                </div>
            </div>
        </CardContent>

        <!-- Alert Dialog (Ditaruh di luar layout flex) -->
        <AlertDialog :open="isDialogOpen" @update:open="isDialogOpen = $event">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle> Hapus file ini? </AlertDialogTitle>
                    <AlertDialogDescription>
                        File
                        <span class="font-semibold text-foreground"
                            >"{{ file.name }}"</span
                        >
                        akan dihapus secara permanen. Tindakan ini tidak dapat
                        dibatalkan.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel :disabled="isLoading">
                        Batal
                    </AlertDialogCancel>
                    <AlertDialogAction
                        class="bg-destructive hover:bg-destructive/90 text-destructive-foreground"
                        :disabled="isLoading"
                        @click.prevent="handleDelete(file.path)"
                    >
                        <Loader2
                            v-if="isLoading"
                            class="w-4 h-4 animate-spin mr-2"
                        />
                        Hapus
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </Card>
</template>
