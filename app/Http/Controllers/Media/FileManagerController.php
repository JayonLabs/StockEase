<?php

namespace App\Http\Controllers\Media;

use App\Helpers\FormatBytes;
use App\Http\Controllers\Controller;
use App\Http\Requests\Media\DestroyFileRequest;
use App\Http\Requests\Media\DownloadFileRequest;
use App\Http\Requests\Media\StoreFileRequest;
use Carbon\Carbon;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class FileManagerController extends Controller
{
    /**
     * Display a listing of files with optional search and filter functionality.
     *
     * This method retrieves all files from storage, applies search and filter
     * conditions (if provided in the request), and returns a paginated result
     * to be rendered with Inertia.
     *
     * Query Parameters:
     * - search (string, optional): Filter files by matching name.
     * - file_filter (string, optional): Filter files by extension/type.
     *   Use "all" to disable this filter.
     * - page (int, optional): Current page for pagination (default: 1).
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $files = collect($this->_getFiles());

        if ($request->filled('search')) {
            $files = collect($this->_search($files, $request->search));
        }

        if ($request->filled('file_filter') && $request->file_filter !== 'all') {
            $files = collect($this->_fileTypeFilter($files, $request->file_filter));
        }

        $page = $request->input('page', 1);
        $perPage = 20;

        $paginated = new LengthAwarePaginator(
            $files->forPage($page, $perPage),
            $files->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );

        return Inertia::render('FileManager/Index', [
            'files' => $paginated,
        ]);
    }

    /**
     * Get the company-specific base path for file storage.
     *
     * Accepts paths with or without the 'uploads/' prefix.
     * Returns a path scoped to the current user's company:
     *   'uploads/{company_id}/{filename}'
     */
    protected function getStoragePath(string $path = ''): string
    {
        $companyId = Auth::user()?->company_id ?? 'common';

        if (empty(trim($path))) {
            return 'uploads/'.$companyId;
        }

        $cleanPath = preg_replace('#^uploads/?#', '', $path);

        return 'uploads/'.$companyId.'/'.ltrim($cleanPath, '/');
    }

    /**
     * Get all files in the given path scoped to the current company.
     *
     * @param  string  $path
     * @return array
     */
    protected function _getFiles($path = '')
    {
        $disk = Storage::disk('local');
        $basePath = $this->getStoragePath($path);

        $allFiles = collect($disk->allFiles($basePath))
            ->filter(fn ($file) => ! preg_match('/^\./', basename($file)))
            ->values();

        if ($allFiles->isEmpty()) {
            return [];
        }

        $companyId = Auth::user()?->company_id ?? 'common';
        $prefix = 'uploads/'.$companyId.'/';

        return $allFiles->map(function ($file) use ($disk, $prefix) {
            $publicPath = 'uploads/'.substr($file, strlen($prefix));

            return [
                'path' => $publicPath,
                'name' => basename($file),
                'size' => FormatBytes::formatBytes($disk->size($file)),
                'last_modified' => Carbon::createFromTimestamp($disk->lastModified($file))->format('d M Y'),
                'file_extension' => pathinfo($file, PATHINFO_EXTENSION),
            ];
        })
            ->sortByDesc('last_modified')
            ->values();
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return RedirectResponse
     *
     * @throws \Throwable
     */
    public function store(StoreFileRequest $request)
    {
        try {
            $data = $request->validated();
            $files = $data['file'];
            $filePath = $this->getStoragePath();
            $disk = Storage::disk('local');

            foreach ($files as $file) {
                $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                $extension = $file->getClientOriginalExtension();

                $fileName = $originalName.'.'.$extension;
                $counter = 1;

                while ($disk->exists($filePath.'/'.$fileName)) {
                    $fileName = $originalName." ({$counter}).".$extension;
                    $counter++;
                }

                $stream = fopen($file->getRealPath(), 'r');
                $disk->put($filePath.'/'.$fileName, $stream);
                fclose($stream);
            }

            return redirect()->back()->with('success', 'File uploaded successfully');
        } catch (\Throwable $th) {
            return redirect()->back()->with('error', 'File upload failed');
        }
    }

    /**
     * Download a file from the given path.
     *
     * @param  string  $file
     * @return BinaryFileResponse
     *
     * @throws NotFoundHttpException
     */
    public function download(DownloadFileRequest $request)
    {
        $file = $this->getStoragePath($request->validated('file'));

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('local');

        if (! $disk->exists($file)) {
            abort(404, 'File not found');
        }

        return $disk->download($file);
    }

    /**
     * Delete a file from the given path.
     *
     * @param  string  $filePath
     * @return JsonResponse
     *
     * @throws NotFoundHttpException
     */
    public function destroy(DestroyFileRequest $request)
    {
        $requestedFile = $request->validated('file');
        $filePath = $this->getStoragePath($requestedFile);

        try {
            $disk = Storage::disk('local');
            $exists = $disk->exists($filePath);

            if (! $exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'File not found',
                ], 404);
            }

            $disk->delete($filePath);

            return response()->json([
                'success' => true,
                'message' => 'File deleted successfully',
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'data' => $th->getMessage(),
            ]);
        }
    }

    /**
     * Search for files by name
     *
     * @param  string  $search
     * @return LengthAwarePaginator
     */
    private function _search($files, $search)
    {
        return $files->filter(function ($file) use ($search) {
            return Str::contains(Str::lower($file['name']), Str::lower($search));
        })->values();
    }

    /**
     * Filter files by extension/type.
     *
     * @param  Collection  $files
     * @param  string  $file_filter  The file extension/type to filter by.
     * @return Collection
     */
    private function _fileTypeFilter($files, $file_filter)
    {
        return $files->filter(function ($file) use ($file_filter) {
            return $file['file_extension'] === $file_filter;
        })->values();
    }
}
