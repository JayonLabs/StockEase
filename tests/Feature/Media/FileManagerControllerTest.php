<?php

use App\Models\Company;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(LazilyRefreshDatabase::class);

function createCompany(): Company
{
    $company = Company::create([
        'name' => 'Test Company',
        'slug' => 'test-company-'.uniqid(),
        'is_active' => true,
    ]);

    // File manager membutuhkan plan Enterprise; buat subscription jika plan sudah ada.
    $enterprise = Plan::where('slug', 'enterprise')->first();
    if ($enterprise) {
        $company->subscription()->create([
            'plan_id' => $enterprise->id,
            'status' => 'active',
            'starts_at' => now(),
        ]);
    }

    return $company;
}

function fileManagerAdmin(): User
{
    $company = createCompany();

    return User::factory()->create(['role' => 'admin', 'company_id' => $company->id]);
}

function fileManagerWarehouse(): User
{
    $company = createCompany();

    return User::factory()->create(['role' => 'warehouse', 'company_id' => $company->id]);
}

function fileManagerCashier(): User
{
    $company = createCompany();

    return User::factory()->create(['role' => 'cashier', 'company_id' => $company->id]);
}

function fileManagerUserWithoutPermission(): User
{
    $company = createCompany();
    $user = User::factory()->create(['role' => 'cashier', 'company_id' => $company->id]);
    $user->syncRoles([]);

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user->fresh();
}

beforeEach(function () {
    Storage::fake('local');
    Plan::factory()->pemula()->create();
    Plan::factory()->enterprise()->create();
});

// ============================================================
// Authorization Tests
// ============================================================

describe('Authorization', function () {
    it('redirects unauthenticated user from index', function () {
        get(route('file-manager.index'))->assertRedirect(route('login'));
    });

    it('redirects unauthenticated user from download', function () {
        get(route('file-manager.download', ['file' => 'uploads/test.pdf']))->assertRedirect(route('login'));
    });

    it('redirects unauthenticated user from destroy', function () {
        \Pest\Laravel\delete(route('file-manager.destroy'))->assertRedirect(route('login'));
    });

    it('redirects unauthenticated user from store', function () {
        \Pest\Laravel\post(route('file-manager.store'))->assertRedirect(route('login'));
    });

    it('denies user without view_file_manager permission on index', function () {
        actingAs(fileManagerUserWithoutPermission())
            ->get(route('file-manager.index'))
            ->assertForbidden();
    });

    it('denies user without download_files permission on download', function () {
        $user = fileManagerUserWithoutPermission();
        Storage::disk('local')->put('uploads/'.$user->company_id.'/test.pdf', 'content');

        actingAs($user)
            ->get(route('file-manager.download', ['file' => 'uploads/test.pdf']))
            ->assertForbidden();
    });

    it('denies user without delete_files permission on destroy', function () {
        actingAs(fileManagerUserWithoutPermission())
            ->deleteJson(route('file-manager.destroy'), ['file' => 'uploads/test.pdf'])
            ->assertForbidden();
    });

    it('denies user without upload_files permission on store', function () {
        $file = UploadedFile::fake()->create('document.pdf', 10);

        actingAs(fileManagerUserWithoutPermission())
            ->post(route('file-manager.store'), ['file' => [$file]])
            ->assertForbidden();
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
        $user = fileManagerAdmin();
        $disk = Storage::disk('local');
        $disk->put('uploads/'.$user->company_id.'/test.pdf', 'test content');

        actingAs($user)->get(route('file-manager.index'))
            ->assertInertia(
                fn ($page) => $page->has('files.data', 1)
            );
    });

    it('includes file metadata when files exist', function () {
        $user = fileManagerAdmin();
        $disk = Storage::disk('local');
        $disk->put('uploads/'.$user->company_id.'/test.pdf', 'test content');

        actingAs($user)->get(route('file-manager.index'))
            ->assertInertia(
                fn ($page) => $page
                    ->where('files.total', 1)
            );
    });

    it('returns paginated files', function () {
        $user = fileManagerAdmin();
        $disk = Storage::disk('local');

        for ($i = 0; $i < 25; $i++) {
            $disk->put('uploads/'.$user->company_id.'/file'.$i.'.pdf', 'content');
        }

        actingAs($user)->get(route('file-manager.index'))
            ->assertInertia(
                fn ($page) => $page->has('files.data', 20)
            );
    });

    it('returns second page of files', function () {
        $user = fileManagerAdmin();
        $disk = Storage::disk('local');

        for ($i = 0; $i < 25; $i++) {
            $disk->put('uploads/'.$user->company_id.'/file'.$i.'.pdf', 'content');
        }

        actingAs($user)->get(route('file-manager.index', ['page' => 2]))
            ->assertInertia(
                fn ($page) => $page->has('files.data', 5)
            );
    });

    it('excludes hidden files starting with dot', function () {
        $user = fileManagerAdmin();
        $disk = Storage::disk('local');

        $disk->put('uploads/'.$user->company_id.'/visible.pdf', 'content');
        $disk->put('uploads/'.$user->company_id.'/.hidden.pdf', 'hidden');

        actingAs($user)->get(route('file-manager.index'))
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
        $user = fileManagerAdmin();
        $disk = Storage::disk('local');
        $disk->put('uploads/'.$user->company_id.'/document.pdf', 'content');
        $disk->put('uploads/'.$user->company_id.'/image.pdf', 'content');

        actingAs($user)->get(route('file-manager.index', ['search' => 'doc']))
            ->assertInertia(
                fn ($page) => $page->has('files.data', 1)
            );
    });

    it('search is case insensitive', function () {
        $user = fileManagerAdmin();
        $disk = Storage::disk('local');
        $disk->put('uploads/'.$user->company_id.'/DOCUMENT.pdf', 'content');

        actingAs($user)->get(route('file-manager.index', ['search' => 'document']))
            ->assertInertia(
                fn ($page) => $page->has('files.data', 1)
            );
    });

    it('returns empty when search has no match', function () {
        $user = fileManagerAdmin();
        $disk = Storage::disk('local');
        $disk->put('uploads/'.$user->company_id.'/test.pdf', 'content');

        actingAs($user)->get(route('file-manager.index', ['search' => 'nonexistent']))
            ->assertInertia(
                fn ($page) => $page->has('files.data', 0)
            );
    });

    it('returns all files when search is empty', function () {
        $user = fileManagerAdmin();
        $disk = Storage::disk('local');
        $disk->put('uploads/'.$user->company_id.'/file1.pdf', 'content');
        $disk->put('uploads/'.$user->company_id.'/file2.pdf', 'content');

        actingAs($user)->get(route('file-manager.index', ['search' => '']))
            ->assertInertia(
                fn ($page) => $page->has('files.data', 2)
            );
    });

    it('can search with partial match', function () {
        $user = fileManagerAdmin();
        $disk = Storage::disk('local');
        $disk->put('uploads/'.$user->company_id.'/invoice-2024.pdf', 'content');
        $disk->put('uploads/'.$user->company_id.'/report.pdf', 'content');

        actingAs($user)->get(route('file-manager.index', ['search' => 'invoice']))
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
        $user = fileManagerAdmin();
        $disk = Storage::disk('local');
        $disk->put('uploads/'.$user->company_id.'/document.pdf', 'content');
        $disk->put('uploads/'.$user->company_id.'/image.xlsx', 'content');

        actingAs($user)->get(route('file-manager.index', ['file_filter' => 'pdf']))
            ->assertInertia(
                fn ($page) => $page->has('files.data', 1)
            );
    });

    it('can filter by xlsx extension', function () {
        $user = fileManagerAdmin();
        $disk = Storage::disk('local');
        $disk->put('uploads/'.$user->company_id.'/data.xlsx', 'content');
        $disk->put('uploads/'.$user->company_id.'/doc.pdf', 'content');

        actingAs($user)->get(route('file-manager.index', ['file_filter' => 'xlsx']))
            ->assertInertia(
                fn ($page) => $page->has('files.data', 1)
            );
    });

    it('can filter by csv extension', function () {
        $user = fileManagerAdmin();
        $disk = Storage::disk('local');
        $disk->put('uploads/'.$user->company_id.'/data.csv', 'content');
        $disk->put('uploads/'.$user->company_id.'/doc.pdf', 'content');

        actingAs($user)->get(route('file-manager.index', ['file_filter' => 'csv']))
            ->assertInertia(
                fn ($page) => $page->has('files.data', 1)
            );
    });

    it('can filter by xls extension', function () {
        $user = fileManagerAdmin();
        $disk = Storage::disk('local');
        $disk->put('uploads/'.$user->company_id.'/data.xls', 'content');
        $disk->put('uploads/'.$user->company_id.'/doc.pdf', 'content');

        actingAs($user)->get(route('file-manager.index', ['file_filter' => 'xls']))
            ->assertInertia(
                fn ($page) => $page->has('files.data', 1)
            );
    });

    it('returns all files when filter is all', function () {
        $user = fileManagerAdmin();
        $disk = Storage::disk('local');
        $disk->put('uploads/'.$user->company_id.'/file1.pdf', 'content');
        $disk->put('uploads/'.$user->company_id.'/file2.xlsx', 'content');
        $disk->put('uploads/'.$user->company_id.'/file3.csv', 'content');

        actingAs($user)->get(route('file-manager.index', ['file_filter' => 'all']))
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
        $user = fileManagerAdmin();
        $disk = Storage::disk('local');
        $disk->put('uploads/'.$user->company_id.'/invoice.pdf', 'content');
        $disk->put('uploads/'.$user->company_id.'/invoice.xlsx', 'content');
        $disk->put('uploads/'.$user->company_id.'/report.pdf', 'content');

        actingAs($user)->get(route('file-manager.index', [
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
        $user = fileManagerAdmin();
        $file = UploadedFile::fake()->create('document.pdf', 10);

        actingAs($user)->post(route('file-manager.store'), [
            'file' => [$file],
        ])->assertRedirect();

        /** @var Filesystem $storage */
        $storage = Storage::disk('local');

        $storage->assertExists('uploads/'.$user->company_id.'/document.pdf');
    });

    it('can upload multiple files', function () {
        $user = fileManagerAdmin();
        $file1 = UploadedFile::fake()->create('doc1.pdf', 10);
        $file2 = UploadedFile::fake()->create('doc2.pdf', 10);

        /** @var Filesystem $disk */
        $disk = Storage::disk('local');

        actingAs($user)->post(route('file-manager.store'), [
            'file' => [$file1, $file2],
        ])->assertRedirect();

        $disk->assertExists('uploads/'.$user->company_id.'/doc1.pdf');
        $disk->assertExists('uploads/'.$user->company_id.'/doc2.pdf');
    });

    it('uploads file to correct path', function () {
        $user = fileManagerAdmin();
        $file = UploadedFile::fake()->create('test.pdf', 10);
        /** @var Filesystem $disk */
        $disk = Storage::disk('local');

        actingAs($user)->post(route('file-manager.store'), [
            'file' => [$file],
        ])->assertRedirect();

        $disk->assertExists('uploads/'.$user->company_id.'/test.pdf');
    });

    it('returns success message after upload', function () {
        $user = fileManagerAdmin();
        $file = UploadedFile::fake()->create('document.pdf', 10);

        actingAs($user)->post(route('file-manager.store'), [
            'file' => [$file],
        ])->assertSessionHas('success');
    });

    it('can upload xlsx file', function () {
        $user = fileManagerAdmin();
        $file = UploadedFile::fake()->create('data.xlsx', 10, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        /** @var Filesystem $disk */
        $disk = Storage::disk('local');
        actingAs($user)->post(route('file-manager.store'), [
            'file' => [$file],
        ])->assertRedirect();

        $disk->assertExists('uploads/'.$user->company_id.'/data.xlsx');
    });

    it('can upload csv file', function () {
        $user = fileManagerAdmin();
        $file = UploadedFile::fake()->create('data.csv', 10, 'text/csv');
        /** @var Filesystem $disk */
        $disk = Storage::disk('local');
        actingAs($user)->post(route('file-manager.store'), [
            'file' => [$file],
        ])->assertRedirect();

        $disk->assertExists('uploads/'.$user->company_id.'/data.csv');
    });

    it('can upload xls file', function () {
        $user = fileManagerAdmin();
        $file = UploadedFile::fake()->create('data.xls', 10, 'application/vnd.ms-excel');
        /** @var Filesystem $disk */
        $disk = Storage::disk('local');
        actingAs($user)->post(route('file-manager.store'), [
            'file' => [$file],
        ])->assertRedirect();

        $disk->assertExists('uploads/'.$user->company_id.'/data.xls');
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
        $user = fileManagerAdmin();
        /** @var Filesystem $disk */
        $disk = Storage::disk('local');
        $disk->put('uploads/'.$user->company_id.'/document.pdf', 'original');

        $file = UploadedFile::fake()->create('document.pdf', 10);

        actingAs($user)->post(route('file-manager.store'), [
            'file' => [$file],
        ])->assertRedirect();

        $disk->assertExists('uploads/'.$user->company_id.'/document (1).pdf');
    });

    it('increments counter for multiple collisions', function () {
        $user = fileManagerAdmin();
        /** @var Filesystem $disk */
        $disk = Storage::disk('local');
        $disk->put('uploads/'.$user->company_id.'/document.pdf', 'original');
        $disk->put('uploads/'.$user->company_id.'/document (1).pdf', 'second');

        $file = UploadedFile::fake()->create('document.pdf', 10);

        actingAs($user)->post(route('file-manager.store'), [
            'file' => [$file],
        ])->assertRedirect();

        $disk->assertExists('uploads/'.$user->company_id.'/document (2).pdf');
    });
});

// ============================================================
// Download Tests
// ============================================================

describe('Download', function () {
    it('can download an existing file', function () {
        $user = fileManagerAdmin();
        $disk = Storage::disk('local');
        $disk->put('uploads/'.$user->company_id.'/document.pdf', 'content');

        actingAs($user)->get(route('file-manager.download', ['file' => 'uploads/document.pdf']))
            ->assertSuccessful();
    });

    it('returns 404 for non-existent file', function () {
        actingAs(fileManagerAdmin())->get(route('file-manager.download', ['file' => 'uploads/nonexistent.pdf']))
            ->assertStatus(404);
    });

    it('allows user with download_files permission to download', function () {
        $user = fileManagerCashier();
        $disk = Storage::disk('local');
        $disk->put('uploads/'.$user->company_id.'/document.pdf', 'content');

        actingAs($user)->get(route('file-manager.download', ['file' => 'uploads/document.pdf']))
            ->assertSuccessful();
    });
});

// ============================================================
// Destroy (Delete) Tests
// ============================================================

describe('Destroy (Delete)', function () {
    it('can delete an existing file', function () {
        $user = fileManagerAdmin();
        /** @var Filesystem $disk */
        $disk = Storage::disk('local');
        $disk->put('uploads/'.$user->company_id.'/document.pdf', 'content');

        actingAs($user)->deleteJson(route('file-manager.destroy'), [
            'file' => 'uploads/document.pdf',
        ])
            ->assertSuccessful()
            ->assertJson(['success' => true]);

        $disk->assertMissing('uploads/'.$user->company_id.'/document.pdf');
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

    it('allows user with delete_files permission to delete files', function () {
        $user = fileManagerCashier();
        /** @var Filesystem $disk */
        $disk = Storage::disk('local');
        $disk->put('uploads/'.$user->company_id.'/document.pdf', 'content');

        actingAs($user)->deleteJson(route('file-manager.destroy'), [
            'file' => 'uploads/document.pdf',
        ])->assertSuccessful();

        $disk->assertMissing('uploads/'.$user->company_id.'/document.pdf');
    });
});

// ============================================================
// Security — Path Traversal (SEC-02)
// ============================================================

describe('Security — Path Traversal', function () {
    it('rejects download of file outside uploads/ directory', function () {
        Storage::disk('local')->put('framework/sessions/secret', 'sensitive');

        actingAs(fileManagerAdmin())
            ->get(route('file-manager.download', ['file' => 'framework/sessions/secret']))
            ->assertSessionHasErrors('file');
    });

    it('rejects download with path traversal sequence', function () {
        $user = fileManagerAdmin();
        Storage::disk('local')->put('uploads/'.$user->company_id.'/legit.pdf', 'content');

        actingAs($user)
            ->get(route('file-manager.download', ['file' => 'uploads/../framework/sessions/secret']))
            ->assertSessionHasErrors('file');
    });

    it('rejects download when file param is missing', function () {
        actingAs(fileManagerAdmin())
            ->get(route('file-manager.download'))
            ->assertSessionHasErrors('file');
    });

    it('allows download of file inside uploads/', function () {
        $user = fileManagerAdmin();
        Storage::disk('local')->put('uploads/'.$user->company_id.'/allowed.pdf', 'content');

        actingAs($user)
            ->get(route('file-manager.download', ['file' => 'uploads/allowed.pdf']))
            ->assertSuccessful();
    });

    it('rejects destroy of file outside uploads/ directory', function () {
        Storage::disk('local')->put('framework/cache/data/xyz', 'cached');

        actingAs(fileManagerAdmin())
            ->deleteJson(route('file-manager.destroy'), ['file' => 'framework/cache/data/xyz'])
            ->assertStatus(422);
    });

    it('rejects destroy with path traversal sequence', function () {
        $user = fileManagerAdmin();
        Storage::disk('local')->put('uploads/'.$user->company_id.'/legit.pdf', 'content');

        actingAs($user)
            ->deleteJson(route('file-manager.destroy'), ['file' => 'uploads/../framework/cache/data'])
            ->assertStatus(422);
    });

    it('rejects destroy when file param is missing', function () {
        actingAs(fileManagerAdmin())
            ->deleteJson(route('file-manager.destroy'), [])
            ->assertStatus(422);
    });

    it('does not delete file outside uploads/ when traversal attempted', function () {
        /** @var Filesystem $disk */
        $disk = Storage::disk('local');
        $disk->put('framework/sessions/secret', 'sensitive');

        actingAs(fileManagerAdmin())
            ->deleteJson(route('file-manager.destroy'), ['file' => 'framework/sessions/secret'])
            ->assertStatus(422);

        $disk->assertExists('framework/sessions/secret');
    });
});

// ============================================================
// Stream Upload — BP-03 — Content Integrity
// ============================================================

describe('Stream Upload — Content Integrity', function () {
    it('uploads file via streaming with correct filename', function () {
        $user = fileManagerAdmin();
        $file = UploadedFile::fake()->create('stream-test.pdf', 5);

        actingAs($user)->post(route('file-manager.store'), [
            'file' => [$file],
        ])->assertRedirect();

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('local');
        $disk->assertExists('uploads/'.$user->company_id.'/stream-test.pdf');
    });

    it('uploads file via streaming when collision occurs', function () {
        $user = fileManagerAdmin();
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('local');
        $disk->put('uploads/'.$user->company_id.'/stream-dup.pdf', 'original');

        $file = UploadedFile::fake()->create('stream-dup.pdf', 5);

        actingAs($user)->post(route('file-manager.store'), [
            'file' => [$file],
        ])->assertRedirect();

        $disk->assertExists('uploads/'.$user->company_id.'/stream-dup (1).pdf');
    });

    it('uploads multiple files correctly via streaming', function () {
        $user = fileManagerAdmin();
        $file1 = UploadedFile::fake()->create('stream-a.pdf', 3);
        $file2 = UploadedFile::fake()->create('stream-b.pdf', 5);

        actingAs($user)->post(route('file-manager.store'), [
            'file' => [$file1, $file2],
        ])->assertRedirect();

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('local');
        $disk->assertExists('uploads/'.$user->company_id.'/stream-a.pdf');
        $disk->assertExists('uploads/'.$user->company_id.'/stream-b.pdf');
    });

    it('uploads a moderately large file via streaming', function () {
        $user = fileManagerAdmin();
        $sizeKb = 512;
        $file = UploadedFile::fake()->create('large-stream.pdf', $sizeKb);

        actingAs($user)->post(route('file-manager.store'), [
            'file' => [$file],
        ])->assertRedirect();

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('local');
        $disk->assertExists('uploads/'.$user->company_id.'/large-stream.pdf');
    });

    it('uploads file with xlsx mime type via streaming', function () {
        $user = fileManagerAdmin();
        $file = UploadedFile::fake()->create('stream-data.xlsx', 10);

        actingAs($user)->post(route('file-manager.store'), [
            'file' => [$file],
        ])->assertRedirect();

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('local');
        $disk->assertExists('uploads/'.$user->company_id.'/stream-data.xlsx');
    });

    it('uploads file with csv mime type via streaming', function () {
        $user = fileManagerAdmin();
        $file = UploadedFile::fake()->create('stream-data.csv', 10);

        actingAs($user)->post(route('file-manager.store'), [
            'file' => [$file],
        ])->assertRedirect();

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('local');
        $disk->assertExists('uploads/'.$user->company_id.'/stream-data.csv');
    });
});

// ============================================================
// Edge Cases
// ============================================================

describe('Edge Cases', function () {
    it('handles files with special characters in name', function () {
        $user = fileManagerAdmin();
        $disk = Storage::disk('local');
        $disk->put('uploads/'.$user->company_id.'/file with spaces.pdf', 'content');

        actingAs($user)->get(route('file-manager.index', ['search' => 'spaces']))
            ->assertInertia(
                fn ($page) => $page->has('files.data', 1)
            );
    });

    it('sorts files by last modified descending', function () {
        $user = fileManagerAdmin();
        $disk = Storage::disk('local');
        $disk->put('uploads/'.$user->company_id.'/old.pdf', 'old content');
        $disk->put('uploads/'.$user->company_id.'/new.pdf', 'new content');

        actingAs($user)->get(route('file-manager.index'))
            ->assertInertia(
                fn ($page) => $page->where('files.data.0.name', 'new.pdf')
            );
    });

    it('preserves query params in pagination', function () {
        $user = fileManagerAdmin();
        $disk = Storage::disk('local');
        for ($i = 0; $i < 25; $i++) {
            $disk->put('uploads/'.$user->company_id.'/file'.$i.'.pdf', 'content');
        }

        actingAs($user)->get(route('file-manager.index', ['search' => 'file', 'page' => 2]))
            ->assertInertia(
                fn ($page) => $page->where('files.current_page', 2)
            );
    });
});
