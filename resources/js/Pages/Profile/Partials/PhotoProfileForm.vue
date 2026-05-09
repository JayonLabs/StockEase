<script setup>
import { Button } from '@/Components/ui/button';
import { Loader2, Pencil } from 'lucide-vue-next';
import VueCropper from 'vue-cropperjs/VueCropper.js';
import 'cropperjs/dist/cropper.css';
import { ref, nextTick } from 'vue';
import { toast } from 'vue-sonner';
import { DotsVerticalIcon } from '@radix-icons/vue';
import axios from 'axios';

import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';

import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';

const showCropperModal = ref(false);

const imgSrc = ref(null);
const cropper = ref(null);
const isUploadingPhoto = ref(false);

const fileInput = ref(null);

const emit = defineEmits(['photo-updated']);

const openFilePicker = () => {
    fileInput.value?.click();
};

const setImage = (e) => {
    const file = e.target.files[0];
    if (!file?.type?.includes('image/')) {
        toast.error('Silahkan pilih file gambar');
        return;
    }

    const reader = new FileReader();
    reader.onload = (event) => {
        imgSrc.value = event.target.result;
        showCropperModal.value = true;

        nextTick(() => {
            cropper.value?.replace(event.target.result);
        });
    };
    reader.readAsDataURL(file);
};

const handleCrop = () => {
    isUploadingPhoto.value = true;
    const canvas = cropper.value?.getCroppedCanvas();

    if (canvas) {
        canvas.toBlob((blob) => {
            if (blob) {
                const file = new File([blob], 'cropped.jpg', {
                    type: 'image/jpeg',
                });

                let formData = new FormData();

                formData.append('photo_profile', file);

                axios
                    .post(route('profile.photo-profile'), formData, {
                        headers: {
                            'Content-Type': 'multipart/form-data',
                        },
                    })
                    .then((response) => {
                        emit('photo-updated');
                        toast.success('Photo Profile berhasil diperbarui');
                        showCropperModal.value = false;
                        isUploadingPhoto.value = false;
                    })
                    .catch((error) => {
                        console.error(error);
                        toast.error('Photo Profile gagal diperbarui');
                        showCropperModal.value = false;
                        isUploadingPhoto.value = false;
                    });
            }
        }, 'image/jpeg');
    }
};

const deletePhotoProfile = () => {
    axios
        .delete(route('profile.destroy-photo-profile'))
        .then((response) => {
            emit('photo-updated');
            toast.success('Photo Profile berhasil dihapus');
        })
        .catch((error) => {
            console.error(error);
            toast.error('Photo Profile gagal dihapus');
        });
};
</script>

<template>
    <!-- Tombol Edit -->
    <DropdownMenu>
        <DropdownMenuTrigger>
            <DotsVerticalIcon class="w-4 h-4 cursor-pointer" />
        </DropdownMenuTrigger>
        <DropdownMenuContent>
            <DropdownMenuLabel>Profile</DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuItem class="cursor-pointer" @click="openFilePicker">
                Ganti Photo Profile
            </DropdownMenuItem>
            <DropdownMenuItem
                v-if="$page.props.auth.user.photo_profile"
                class="cursor-pointer text-red-500"
                @click="deletePhotoProfile"
            >
                Hapus Photo Profile
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>

    <!-- Input file hidden -->
    <input
        ref="fileInput"
        type="file"
        accept="image/*"
        class="hidden"
        @change="setImage"
    />

    <!-- Modal Cropper -->
    <Dialog v-model:open="showCropperModal">
        <DialogContent class="max-w-3xl">
            <DialogHeader>
                <DialogTitle>Crop Gambar</DialogTitle>
            </DialogHeader>

            <div class="flex flex-col gap-4">
                <vue-cropper
                    ref="cropper"
                    :src="imgSrc"
                    :guides="true"
                    :view-mode="2"
                    drag-mode="crop"
                    :auto-crop-area="0.5"
                    :min-container-width="250"
                    :min-container-height="180"
                    :background="true"
                    :rotatable="true"
                    :aspect-ratio="1 / 1"
                    :img-style="{
                        width: '100%',
                        maxHeight: '400px',
                    }"
                />

                <div class="flex justify-end gap-2">
                    <Button variant="ghost" @click="showCropperModal = false">
                        Batal
                    </Button>
                    <Button
                        :disable="isUploadingPhoto"
                        class="disabled:cursor-not-allowed disabled:opacity-50"
                        @click="handleCrop"
                    >
                        <Loader2 v-if="isUploadingPhoto" class="animate-spin" />
                        Simpan Gambar
                    </Button>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
