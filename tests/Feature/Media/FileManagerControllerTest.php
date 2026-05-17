<?php

use App\Models\User;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

function fileManagerAdmin(): User
{
    return User::role('admin')->first() ?? User::factory()->create(['role' => 'admin']);
}

function fileManagerWarehouse(): User
{
    return User::role('warehouse')->first() ?? User::factory()->create(['role' => 'warehouse']);
}

function fileManagerCashier(): User
{
    return User::role('cashier')->first() ?? User::factory()->create(['role' => 'cashier']);
}

beforeEach(function () {
    Storage::fake('local');
});

// ============================================================
// Authorization Tests
// ============================================================

describe('Authorization', function () {
    it('redirects unauthenticated user', function () {
        get(route('file-manager.index'))->assertRedirect(route('login'));
    });

    it('allows admin to access file manager', function () {
        actingAs(fileManagerAdmin())->get(route('file-manager.index'))
            ->assertSuccessful();
    });

    it('allows warehouse to access file manager', function () {
        actingAs(fileManagerWarehouse())->get(route('file-manager.index'))
            ->assertSuccessful();
    });

    it('allows cashier to access file manager', function () {
        actingAs(fileManagerCashier())->get(route('file-manager.index'))
            ->assertSuccessful();
    });
});

// ============================================================
// Index Tests
// ============================================================

describe('Index', function () {
    it('renders FileManager/Index component', function () {
        actingAs(fileManagerAdmin())->get(route('file-manager.index'))
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page->component('FileManager/Index'));
    });

    it('passes files prop', function () {
        actingAs(fileManagerAdmin())->get(route('file-manager.index'))
            ->assertInertia(fn ($page) => $page->has('files'));
    });

    it('returns files when uploads exist', function () {
        $disk = Storage::disk('local');
        $disk->put('uploads/test.pdf', 'test content');

        actingAs(fileManagerAdmin())->get(route('file-manager.index'))
            ->assertInertia(
                fn ($page) => $page->has('files.data', 1)
            );
    });

    it('includes file metadata when files exist', function () {
        $disk = Storage::disk('local');
        $disk->put('uploads/test.pdf', 'test content');

        actingAs(fileManagerAdmin())->get(route('file-manager.index'))
            ->assertInertia(
                fn ($page) => $page
                    ->where('files.total', 1)
            );
    });

    it('returns paginated files', function () {
        $disk = Storage::disk('local');

        for ($i = 0; $i < 25; $i++) {
            $disk->put("uploads/file{$i}.pdf", 'content');
        }

        actingAs(fileManagerAdmin())->get(route('file-manager.index'))
            ->assertInertia(
                fn ($page) => $page->has('files.data', 20)
            );
    });

    it('returns second page of files', function () {
        $disk = Storage::disk('local');

        for ($i = 0; $i < 25; $i++) {
            $disk->put("uploads/file{$i}.pdf", 'content');
        }

        actingAs(fileManagerAdmin())->get(route('file-manager.index', ['page' => 2]))
            ->assertInertia(
                fn ($page) => $page->has('files.data', 5)
            );
    });

    it('excludes hidden files starting with dot', function () {
        $disk = Storage::disk('local');

        $disk->put('uploads/visible.pdf', 'content');
        $disk->put('uploads/.hidden.pdf', 'hidden');

        actingAs(fileManagerAdmin())->get(route('file-manager.index'))
            ->assertInertia(
                fn ($page) => $page->has('files.data', 1)
            );
    });
});

// ============================================================
// Search Tests
// ============================================================

describe('Search', function () {
    it('can search files by name', function () {
        $disk = Storage::disk('local');
        $disk->put('uploads/document.pdf', 'content');
        $disk->put('uploads/image.pdf', 'content');

        actingAs(fileManagerAdmin())->get(route('file-manager.index', ['search' => 'doc']))
            ->assertInertia(
                fn ($page) => $page->has('files.data', 1)
            );
    });

    it('search is case insensitive', function () {
        $disk = Storage::disk('local');
        $disk->put('uploads/DOCUMENT.pdf', 'content');

        actingAs(fileManagerAdmin())->get(route('file-manager.index', ['search' => 'document']))
            ->assertInertia(
                fn ($page) => $page->has('files.data', 1)
            );
    });

    it('returns empty when search has no match', function () {
        $disk = Storage::disk('local');
        $disk->put('uploads/test.pdf', 'content');

        actingAs(fileManagerAdmin())->get(route('file-manager.index', ['search' => 'nonexistent']))
            ->assertInertia(
                fn ($page) => $page->has('files.data', 0)
            );
    });

    it('returns all files when search is empty', function () {
        $disk = Storage::disk('local');
        $disk->put('uploads/file1.pdf', 'content');
        $disk->put('uploads/file2.pdf', 'content');

        actingAs(fileManagerAdmin())->get(route('file-manager.index', ['search' => '']))
            ->assertInertia(
                fn ($page) => $page->has('files.data', 2)
            );
    });

    it('can search with partial match', function () {
        $disk = Storage::disk('local');
        $disk->put('uploads/invoice-2024.pdf', 'content');
        $disk->put('uploads/report.pdf', 'content');

        actingAs(fileManagerAdmin())->get(route('file-manager.index', ['search' => 'invoice']))
            ->assertInertia(
                fn ($page) => $page->has('files.data', 1)
            );
    });
});

// ============================================================
// File Type Filter Tests
// ============================================================

describe('File Type Filter', function () {
    it('can filter by pdf extension', function () {
        $disk = Storage::disk('local');
        $disk->put('uploads/document.pdf', 'content');
        $disk->put('uploads/image.xlsx', 'content');

        actingAs(fileManagerAdmin())->get(route('file-manager.index', ['file_filter' => 'pdf']))
            ->assertInertia(
                fn ($page) => $page->has('files.data', 1)
            );
    });

    it('can filter by xlsx extension', function () {
        $disk = Storage::disk('local');
        $disk->put('uploads/data.xlsx', 'content');
        $disk->put('uploads/doc.pdf', 'content');

        actingAs(fileManagerAdmin())->get(route('file-manager.index', ['file_filter' => 'xlsx']))
            ->assertInertia(
                fn ($page) => $page->has('files.data', 1)
            );
    });

    it('can filter by csv extension', function () {
        $disk = Storage::disk('local');
        $disk->put('uploads/data.csv', 'content');
        $disk->put('uploads/doc.pdf', 'content');

        actingAs(fileManagerAdmin())->get(route('file-manager.index', ['file_filter' => 'csv']))
            ->assertInertia(
                fn ($page) => $page->has('files.data', 1)
            );
    });

    it('can filter by xls extension', function () {
        $disk = Storage::disk('local');
        $disk->put('uploads/data.xls', 'content');
        $disk->put('uploads/doc.pdf', 'content');

        actingAs(fileManagerAdmin())->get(route('file-manager.index', ['file_filter' => 'xls']))
            ->assertInertia(
                fn ($page) => $page->has('files.data', 1)
            );
    });

    it('returns all files when filter is all', function () {
        $disk = Storage::disk('local');
        $disk->put('uploads/file1.pdf', 'content');
        $disk->put('uploads/file2.xlsx', 'content');
        $disk->put('uploads/file3.csv', 'content');

        actingAs(fileManagerAdmin())->get(route('file-manager.index', ['file_filter' => 'all']))
            ->assertInertia(
                fn ($page) => $page->has('files.data', 3)
            );
    });
});

// ============================================================
// Combined Search and Filter Tests
// ============================================================

describe('Combined Search and Filter', function () {
    it('combines search and file filter', function () {
        $disk = Storage::disk('local');
        $disk->put('uploads/invoice.pdf', 'content');
        $disk->put('uploads/invoice.xlsx', 'content');
        $disk->put('uploads/report.pdf', 'content');

        actingAs(fileManagerAdmin())->get(route('file-manager.index', [
            'search' => 'invoice',
            'file_filter' => 'pdf',
        ]))->assertInertia(
            fn ($page) => $page->has('files.data', 1)
        );
    });
});

// ============================================================
// Store (Upload) Tests
// ============================================================

describe('Store (Upload)', function () {
    it('can upload a single file', function () {
        $file = UploadedFile::fake()->create('document.pdf', 10);

        actingAs(fileManagerAdmin())->post(route('file-manager.store'), [
            'file' => [$file],
        ])->assertRedirect();

        /** @var Filesystem $storage */
        $storage = Storage::disk('local');

        $storage->assertExists('uploads/document.pdf');
    });

    it('can upload multiple files', function () {
        $file1 = UploadedFile::fake()->create('doc1.pdf', 10);
        $file2 = UploadedFile::fake()->create('doc2.pdf', 10);

        /** @var Filesystem $disk */
        $disk = Storage::disk('local');

        actingAs(fileManagerAdmin())->post(route('file-manager.store'), [
            'file' => [$file1, $file2],
        ])->assertRedirect();

        $disk->assertExists('uploads/doc1.pdf');
        $disk->assertExists('uploads/doc2.pdf');
    });

    it('uploads file to correct path', function () {
        $file = UploadedFile::fake()->create('test.pdf', 10);
        /** @var Filesystem $disk */
        $disk = Storage::disk('local');

        actingAs(fileManagerAdmin())->post(route('file-manager.store'), [
            'file' => [$file],
        ])->assertRedirect();

        $disk->assertExists('uploads/test.pdf');
    });

    it('returns success message after upload', function () {
        $file = UploadedFile::fake()->create('document.pdf', 10);

        actingAs(fileManagerAdmin())->post(route('file-manager.store'), [
            'file' => [$file],
        ])->assertSessionHas('success');
    });

    it('can upload xlsx file', function () {
        $file = UploadedFile::fake()->create('data.xlsx', 10, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        /** @var Filesystem $disk */
        $disk = Storage::disk('local');
        actingAs(fileManagerAdmin())->post(route('file-manager.store'), [
            'file' => [$file],
        ])->assertRedirect();

        $disk->assertExists('uploads/data.xlsx');
    });

    it('can upload csv file', function () {
        $file = UploadedFile::fake()->create('data.csv', 10, 'text/csv');
        /** @var Filesystem $disk */
        $disk = Storage::disk('local');
        actingAs(fileManagerAdmin())->post(route('file-manager.store'), [
            'file' => [$file],
        ])->assertRedirect();

        $disk->assertExists('uploads/data.csv');
    });

    it('can upload xls file', function () {
        $file = UploadedFile::fake()->create('data.xls', 10, 'application/vnd.ms-excel');
        /** @var Filesystem $disk */
        $disk = Storage::disk('local');
        actingAs(fileManagerAdmin())->post(route('file-manager.store'), [
            'file' => [$file],
        ])->assertRedirect();

        $disk->assertExists('uploads/data.xls');
    });
});

// ============================================================
// Store Validation Tests
// ============================================================

describe('Store Validation', function () {
    it('fails when file is missing', function () {
        actingAs(fileManagerAdmin())->post(route('file-manager.store'), [])
            ->assertSessionHasErrors('file');
    });

    it('fails when file type is not allowed', function () {
        $file = UploadedFile::fake()->create('document.txt', 10);

        actingAs(fileManagerAdmin())->post(route('file-manager.store'), [
            'file' => [$file],
        ])->assertSessionHasErrors();
    });

    it('fails when file is too small', function () {
        $file = UploadedFile::fake()->create('empty.pdf', 0);

        actingAs(fileManagerAdmin())->post(route('file-manager.store'), [
            'file' => [$file],
        ])->assertSessionHasErrors();
    });

    it('fails when file is too large', function () {
        $file = UploadedFile::fake()->create('large.pdf', 102400);

        actingAs(fileManagerAdmin())->post(route('file-manager.store'), [
            'file' => [$file],
        ])->assertSessionHasErrors();
    });
});

// ============================================================
// Filename Collision Tests
// ============================================================

describe('Filename Collision', function () {
    it('appends counter when file exists', function () {
        /** @var Filesystem $disk */
        $disk = Storage::disk('local');
        $disk->put('uploads/document.pdf', 'original');

        $file = UploadedFile::fake()->create('document.pdf', 10);

        actingAs(fileManagerAdmin())->post(route('file-manager.store'), [
            'file' => [$file],
        ])->assertRedirect();

        $disk->assertExists('uploads/document (1).pdf');
    });

    it('increments counter for multiple collisions', function () {
        /** @var Filesystem $disk */
        $disk = Storage::disk('local');
        $disk->put('uploads/document.pdf', 'original');
        $disk->put('uploads/document (1).pdf', 'second');

        $file = UploadedFile::fake()->create('document.pdf', 10);

        actingAs(fileManagerAdmin())->post(route('file-manager.store'), [
            'file' => [$file],
        ])->assertRedirect();

        $disk->assertExists('uploads/document (2).pdf');
    });
});

// ============================================================
// Download Tests
// ============================================================

describe('Download', function () {
    it('can download an existing file', function () {
        $disk = Storage::disk('local');
        $disk->put('uploads/document.pdf', 'content');

        actingAs(fileManagerAdmin())->get(route('file-manager.download', ['file' => 'uploads/document.pdf']))
            ->assertSuccessful();
    });

    it('returns 404 for non-existent file', function () {
        actingAs(fileManagerAdmin())->get(route('file-manager.download', ['file' => 'uploads/nonexistent.pdf']))
            ->assertStatus(404);
    });

    it('allows any authenticated user to download', function () {
        $disk = Storage::disk('local');
        $disk->put('uploads/document.pdf', 'content');

        actingAs(fileManagerCashier())->get(route('file-manager.download', ['file' => 'uploads/document.pdf']))
            ->assertSuccessful();
    });
});

// ============================================================
// Destroy (Delete) Tests
// ============================================================

describe('Destroy (Delete)', function () {
    it('can delete an existing file', function () {
        /** @var Filesystem $disk */
        $disk = Storage::disk('local');
        $disk->put('uploads/document.pdf', 'content');

        actingAs(fileManagerAdmin())->deleteJson(route('file-manager.destroy'), [
            'file' => 'uploads/document.pdf',
        ])
            ->assertSuccessful()
            ->assertJson(['success' => true]);

        $disk->assertMissing('uploads/document.pdf');
    });

    it('returns 404 when file not found', function () {
        actingAs(fileManagerAdmin())->deleteJson(route('file-manager.destroy'), [
            'file' => 'uploads/nonexistent.pdf',
        ])
            ->assertStatus(404)
            ->assertJson(['success' => false]);
    });

    it('returns error message when delete fails', function () {
        actingAs(fileManagerAdmin())->deleteJson(route('file-manager.destroy'), [
            'file' => 'uploads/nonexistent.pdf',
        ])
            ->assertJsonFragment(['message' => 'File not found']);
    });

    it('allows any authenticated user to delete files', function () {
        /** @var Filesystem $disk */
        $disk = Storage::disk('local');
        $disk->put('uploads/document.pdf', 'content');

        actingAs(fileManagerCashier())->deleteJson(route('file-manager.destroy'), [
            'file' => 'uploads/document.pdf',
        ])->assertSuccessful();

        $disk->assertMissing('uploads/document.pdf');
    });
});

// ============================================================
// Edge Cases
// ============================================================

describe('Edge Cases', function () {
    it('handles files with special characters in name', function () {
        $disk = Storage::disk('local');
        $disk->put('uploads/file with spaces.pdf', 'content');

        actingAs(fileManagerAdmin())->get(route('file-manager.index', ['search' => 'spaces']))
            ->assertInertia(
                fn ($page) => $page->has('files.data', 1)
            );
    });

    it('sorts files by last modified descending', function () {
        $disk = Storage::disk('local');
        $disk->put('uploads/old.pdf', 'old content');
        $disk->put('uploads/new.pdf', 'new content');

        actingAs(fileManagerAdmin())->get(route('file-manager.index'))
            ->assertInertia(
                fn ($page) => $page->where('files.data.0.name', 'new.pdf')
            );
    });

    it('preserves query params in pagination', function () {
        $disk = Storage::disk('local');
        for ($i = 0; $i < 25; $i++) {
            $disk->put("uploads/file{$i}.pdf", 'content');
        }

        actingAs(fileManagerAdmin())->get(route('file-manager.index', ['search' => 'file', 'page' => 2]))
            ->assertInertia(
                fn ($page) => $page->where('files.current_page', 2)
            );
    });
});
