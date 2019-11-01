<?php

namespace S3Files\Controller;

use Doctrine\DBAL\Connection;
use S3Files\Service\Hasher;
use League\Flysystem;
use League\Flysystem\Filesystem;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class MainController extends AbstractController
{
    /**
     * @Route("/", name="filesView")
     * @param Request $request
     * @param Hasher $hasher
     * @param Filesystem $filesystem
     * @param Connection $db
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Exception
     */
    public function view(Request $request, Hasher $hasher, Filesystem $filesystem, Connection $db)
    {
        $path = '';
        $prevPath = '';
        if ($request->query->has('path')) {
            $path = $request->query->get('path');
            $lastSlashPos = strrpos($path, '/');
            if ($lastSlashPos !== false) {
                $prevPath = substr($path, 0, $lastSlashPos);
            }
        }

        // Build breadcrumbs
        $breadcrumbs = ['Home' => ''];
        $pos = 0;
        $activeBreadcrumb = '';
        if (strlen($path) > 0) {
            do {
                $slashPos = strpos($path, '/', $pos);
                $endPos = $slashPos;
                if ($slashPos === false) {
                    $endPos = strlen($path);
                }
                $label = substr($path, $pos, ($endPos - $pos));
                $breadcrumbs[$label] = substr($path, 0, $endPos);
                $pos = $slashPos + 1;
                $activeBreadcrumb = $breadcrumbs[$label];
            } while ($slashPos);
        }

        $files = $filesystem->listContents($path);
        $metaData = $db->fetchAll('SELECT * FROM files WHERE path = ?', [$path]);

        $byFileMetadata = [];
        foreach ($metaData as $row) {
            $byFileMetadata[$row['filename']] = $row;
        }

        foreach ($files as $key => $file) {
            if (!isset($byFileMetadata[$file['path']])) {
                if ($file['type'] === 'file') {
                    $this->addFileMetadata($path, $file['path'], '', '', 'unknown', $db, $hasher);
                    $toMerge = $db->fetchAssoc('SELECT * FROM `files` WHERE filename = ?', [$file['path']]);
                } else {
                    $toMerge = [];
                }
            } else {
                $toMerge = $byFileMetadata[$file['path']];
            }
            if (isset($file['timestamp'])) {
                $file['timestamp'] = date('Y-m-d', intval($file['timestamp']));
            }
            $files[$key] = array_merge($toMerge, $file);
        }

        uasort($files, function($a, $b) {
            if ($a['type'] === $b['type']) {
                return $a['basename'] > $b['basename'];
            }

            return $a['type'] > $b['type'];
        });

        return $this->render('index.twig', [
            'path' => $path,
            'prevPath' => $prevPath,
            'files' => $files,
            'breadcrumbs' => $breadcrumbs,
            'activeBreadcrumb' => $activeBreadcrumb,
            's3DefaultPath' => $this->getParameter('s3.default.path'),
            'downloadUrl' => $this->getParameter('download.url'),
        ]);
    }

    /**
     * @Route("/download/{hash}", name="download")
     * @param Connection $db
     * @param Hasher $hasher
     * @param $hash
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function download(Connection $db, Hasher $hasher, $hash)
    {
        $fileData = ['page_template' => 'default.twig', 'filename' => '', 'path' => ''];
        $error = '';
        try {
            $id = $hasher->readHashPacket($hash);
            $fileData = $db->fetchAssoc('SELECT * FROM `files` WHERE id=?', [$id]);
        } catch (\Exception $e) {
            $error = 'Invalid file url';
        }

        $downloadURL = '';
        $path = $fileData['path'] !== '' ? $fileData['path'] . '/' : '';
        if ($fileData['filename'] !== '') {
            $filename = urlencode(str_replace($path, '', $fileData['filename']));
            $downloadURLPrefix = $this->getParameter('s3.default.path');
            $downloadURL = "{$downloadURLPrefix}/{$path}{$filename}";
        }

        return $this->render("public/{$fileData['page_template']}", [
            'downloadURL' => $downloadURL,
            'file' => str_replace($path, '', $fileData['filename']),
            'error' => $error,
            'name' => $fileData['name'],
            'description' => $fileData['description'],
            's3DefaultPath' => $this->getParameter('s3.default.path'),
            'downloadUrl' => $this->getParameter('download.url'),
        ]);
    }

    /**
     * @Route("/files/upload", name="filesUpload")
     * @param Request $request
     * @param Connection $db
     * @param Filesystem $filesystem
     * @param Hasher $hasher
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function upload(Request $request, Connection $db, Filesystem $filesystem, Hasher $hasher)
    {
        $path = '';
        $prevPath = '';
        $error = '';
        if ($request->query->has('path')) {
            $path = $request->query->get('path');
            $lastSlashPos = strrpos($path, '/');
            if ($lastSlashPos !== false) {
                $prevPath = substr($path, 0, $lastSlashPos);
            }
        }

        // Build breadcrumbs
        $breadcrumbs = ['Home' => ''];
        $pos = 0;
        $activeBreadcrumb = '';
        if (strlen($path) > 0) {
            do {
                $slashPos = strpos($path, '/', $pos);
                $endPos = $slashPos;
                if ($slashPos === false) {
                    $endPos = strlen($path);
                }
                $label = substr($path, $pos, ($endPos - $pos));
                $breadcrumbs[$label] = substr($path, 0, $endPos);
                $pos = $slashPos + 1;
                $activeBreadcrumb = $breadcrumbs[$label];
            } while ($slashPos);
        }

        if ($request->getMethod() === 'POST' && $request->request->has('path') && $request->files->has('uploadFile')) {
            /** @var UploadedFile $file */
            $file = $request->files->get('uploadFile');
            $path = $request->request->get('path');
            try {
                if ($file->isValid()) {
                    $this->addFile(
                        $file,
                        $path,
                        $request->request->get('display-name', ''),
                        $request->request->get('description', ''),
                        $filesystem,
                        $db,
                        $hasher
                    );
                    $this->addFlash('success', 'File uploaded: ' . $file->getClientOriginalName());
                    return $this->redirectToRoute('filesView', [
                        'path' => $path
                    ]);
                } else {
                    $this->addFlash('error', $file->getErrorMessage());
                }
            } catch (Flysystem\Exception $e) {
                $this->addFlash('error', $e->getMessage());
            }

        }

        return $this->render('upload.twig', [
            'path' => $path,
            'prevPath' => $prevPath,
            'breadcrumbs' => $breadcrumbs,
            'activeBreadcrumb' => $activeBreadcrumb,
            's3DefaultPath' => $this->getParameter('s3.default.path'),
            'downloadUrl' => $this->getParameter('download.url'),
        ]);
    }

    /**
     * @Route("/files/delete", name="filesDelete")
     * @param Request $request
     * @param Connection $db
     * @param Filesystem $filesystem
     * @param Hasher $hasher
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function delete(Request $request, Connection $db, Filesystem $filesystem, Hasher $hasher)
    {
        if ($request->getMethod() === 'POST' && $request->request->has('path') && $request->request->has('deleteFile')) {
            // Create new folder at $path/$folderName
            $filePath = str_replace($request->request->get('path') . '/', '', $request->request->get('deleteFile'));
            try {
                if ($filesystem->has($request->request->get('path') . '/' . $filePath)) {
                    $this->deleteFile(
                        $filePath, $request->request->get('path'), $filesystem, $db, $hasher
                    );
                    $this->addFlash('success', 'File deleted: ' . $request->request->get('deleteFile'));
                } else {
                    $this->addFlash('error', 'File does not exist');
                    return $this->redirectToRoute('filesView', [
                        'path' => $request->get('path'),
                    ]);
                }
            } catch (Flysystem\Exception $e) {
                $this->addFlash('error', $e->getMessage());
                return $this->redirectToRoute('filesView', [
                    'path' => $request->get('path'),
                ]);
            }
        }
        return $this->redirectToRoute('filesView', [
            'path' => $request->get('path'),
        ]);
    }

    /**
     * @Route("/files/folders", name="filesFolders")
     * @param Request $request
     * @param Filesystem $filesystem
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function folders(Request $request, Filesystem $filesystem)
    {
        if ($request->getMethod() === 'POST' && $request->request->has('path') && $request->request->has('folderName')) {
            // Create new folder at $path/$folderName
            $filePath = $request->request->get('path') . '/' . $request->request->get('folderName');
            if (!$filesystem->has($filePath)) {
                $filesystem->createDir($filePath);
                $this->addFlash('success', 'Folder created: ' . $request->request->get('folderName'));
            } else {
                $this->addFlash('error', 'Folder already exists');
                return $this->redirectToRoute('filesView', [
                    'path' => $request->get('path'),
                ]);
            }
        }
        return $this->redirectToRoute('filesView', [
            'path' => $request->get('path'),
        ]);
    }

    /**
     * @Route("/files/folders/delete", name="filesFoldersDelete")
     * @param Request $request
     * @param Filesystem $filesystem
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function foldersDelete(Request $request, Filesystem $filesystem)
    {
        if ($request->getMethod() === 'POST' && $request->request->has('deleteFolderName')) {
            // Delete folder at $path/$deleteFolderName
            $filePath = $request->request->get('deleteFolderName');
            if ($filesystem->has($filePath)) {
                $files = $filesystem->listContents($filePath);
                if (count($files) === 0) {
                    $filesystem->deleteDir($filePath);
                    $this->addFlash('success', 'Folder deleted: ' . $request->request->get('deleteFolderName'));
                } else {
                    $this->addFlash('error', 'Folder is not empty');
                    return $this->redirectToRoute('filesView', [
                        'path' => $request->get('path'),
                    ]);
                }
            }
        }
        return $this->redirectToRoute('filesView', [
            'path' => $request->get('path'),
        ]);
    }

    protected function addFile(UploadedFile $file, $path, $name, $description, Filesystem $filesystem, Connection $db, Hasher $hasher)
    {
        $stream = fopen($file->getRealPath(), 'r+');
        $filePath = ($path === '' ? '' : $path . '/').$file->getClientOriginalName();
        $filesystem->writeStream($filePath, $stream, ['visibility' => 'public']);
        fclose($stream);

        $this->addFileMetadata($path, $filePath, $name, $description, $this->getUser()->getEmail(), $db, $hasher);
    }

    protected function addFileMetadata($path, $filePath, $name, $description, $user, Connection $db, Hasher $hasher)
    {
        $db->executeQuery('INSERT INTO `files` (`date`, `created_by`, `hash`, `path`, `filename`, `name`, `description`) VALUES (NOW(), ?, ?, ?, ?, ?, ?)', [
            $user,
            '',
            $path,
            $filePath,
            $name,
            $description
        ]);

        $inserted = $db->fetchAssoc('SELECT * FROM `files` WHERE filename = ?', [$filePath]);
        $id = $inserted['id'];
        $hash = $hasher->makeHashPacket($id);

        $db->executeQuery('UPDATE files SET hash = ? WHERE id = ?', [$hash, $id]);
    }

    protected function deleteFile($filename, $path, Filesystem $filesystem, Connection $db, Hasher $hasher)
    {
        $filePath = ($path === '' ? '' : $path . '/').$filename;
        $filesystem->delete($path . '/' . $filename);

        $db->executeQuery('DELETE FROM `files` WHERE `filename` = ?', [
            $filePath,
        ]);
    }
}
