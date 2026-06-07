<script setup>
import { Button } from '@/Components/ui/button';
import { Icon } from '@iconify/vue';
import { useColorMode } from '@vueuse/core';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
} from '@/Components/ui/dropdown-menu';

const mode = useColorMode();
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button
                aria-label="Ganti tema"
                variant="outline"
                size="icon"
                class="rounded-full"
            >
                <Icon
                    icon="radix-icons:moon"
                    class="h-[1.2rem] w-[1.2rem] rotate-0 scale-100 transition-all dark:-rotate-90 dark:scale-0"
                />
                <Icon
                    icon="radix-icons:sun"
                    class="absolute h-[1.2rem] w-[1.2rem] rotate-90 scale-0 transition-all dark:rotate-0 dark:scale-100"
                />
                <span class="sr-only">Toggle theme</span>
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end">
            <DropdownMenuRadioGroup v-model="mode">
                <DropdownMenuRadioItem value="light" class="cursor-pointer">
                    Light
                </DropdownMenuRadioItem>
                <DropdownMenuRadioItem value="dark" class="cursor-pointer">
                    Dark
                </DropdownMenuRadioItem>
                <DropdownMenuRadioItem value="auto" class="cursor-pointer">
                    Auto
                </DropdownMenuRadioItem>
            </DropdownMenuRadioGroup>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
