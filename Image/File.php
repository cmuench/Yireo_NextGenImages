<?php declare(strict_types=1);

namespace Yireo\NextGenImages\Image;

use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\Driver\File as FileDriver;
use Magento\Framework\Filesystem\File\ReadFactory as FileReadFactory;
use Magento\Framework\View\Asset\File\NotFoundException;
use Yireo\NextGenImages\Exception\ConvertorException;
use Yireo\NextGenImages\Logger\Debugger;

class File
{
    /**
     * @var DirectoryList
     */
    private $directoryList;

    /**
     * @var FileDriver
     */
    private $fileDriver;

    /**
     * @var Debugger
     */
    private $debugger;

    /**
     * @var FileReadFactory
     */
    private $fileReadFactory;

    /**
     * @var UrlConvertor
     */
    private $urlConvertor;

    /**
     * File constructor.
     *
     * @param DirectoryList $directoryList
     * @param FileDriver $fileDriver
     * @param Debugger $debugger
     * @param FileReadFactory $fileReadFactory
     * @param UrlConvertor $urlConvertor
     */
    public function __construct(
        DirectoryList $directoryList,
        FileDriver $fileDriver,
        Debugger $debugger,
        FileReadFactory $fileReadFactory,
        UrlConvertor $urlConvertor
    ) {
        $this->directoryList = $directoryList;
        $this->fileDriver = $fileDriver;
        $this->debugger = $debugger;
        $this->fileReadFactory = $fileReadFactory;
        $this->urlConvertor = $urlConvertor;
    }

    /**
     * @param string $uri
     *
     * @return string
     * @throws ConvertorException
     */
    public function resolve(string $uri): string
    {
        if ($this->fileExists($uri)) {
            return $uri;
        }

        try {
            return $this->urlConvertor->getFilenameFromUrl($uri);
        } catch (NotFoundException $e) {
            throw new ConvertorException($e->getMessage());
        }
    }

    /**
     * @param string $uri
     * @return bool
     * @throws ConvertorException
     */
    public function uriExists(string $uri): bool
    {
        $filePath = $this->resolve($uri);
        if ($this->fileExists($filePath)) {
            return true;
        }

        return false;
    }

    /**
     * @param string $filePath
     * @return bool
     */
    public function fileExists(string $filePath): bool
    {
        try {
            $fileRead = $this->fileReadFactory->create($filePath, 'file');

            $stat = $fileRead->stat();

            if (isset($stat['size'])) {
                return (int)$stat['size'] > 0;
            }

            // fallback if filesystem returns no stats
            return (bool)$fileRead->readAll();
        } catch (FileSystemException $fileSystemException) {
            return false;
        }
    }

    /**
     * @param $filePath
     * @return bool
     * @throws FileSystemException
     */
    public function isWritable($filePath): bool
    {
        if ($this->fileExists($filePath)) {
            return $this->fileDriver->isWritable($filePath);
        }

        return $this->fileDriver->isWritable($this->fileDriver->getParentDirectory($filePath));
    }

    /**
     * @param string $sourceFilename
     * @param string $destinationSuffix
     * @return string
     */
    public function convertSuffix(string $sourceFilename, string $destinationSuffix): string
    {
        return (string)preg_replace('/\.(jpg|jpeg|png)/i', $destinationSuffix, $sourceFilename);
    }

    /**
     * @param string $imagePath
     *
     * @return string
     * @deprecated Removed
     */
    public function getAbsolutePathFromImagePath(string $imagePath): string
    {
        return $this->directoryList->getRoot() . '/pub' . $imagePath;
    }

    /**
     * @param string $filePath
     *
     * @return int
     */
    public function getModificationTime(string $filePath): int
    {
        try {
            $stat = $this->fileDriver->stat($filePath);
            if (!empty($stat['mtime'])) {
                return (int)$stat['mtime'];
            }

            if (!empty($stat['ctime'])) {
                return (int)$stat['ctime'];
            }

            return 0;
        } catch (FileSystemException $e) {
            $this->debugger->debug($e->getMessage(), ['filePath' => $filePath]);
            return 0;
        }
    }

    /**
     * @param string $targetFile
     * @param string $comparisonFile
     *
     * @return bool
     */
    public function isNewerThan(string $targetFile, string $comparisonFile): bool
    {
        if (!$this->fileExists($targetFile)) {
            return false;
        }

        $targetFileModificationTime = $this->getModificationTime($targetFile);
        if ($targetFileModificationTime === 0) {
            return false;
        }

        $comparisonFileModificationTime = $this->getModificationTime($comparisonFile);
        if ($comparisonFileModificationTime === 0) {
            return true;
        }

        if ($targetFileModificationTime > $comparisonFileModificationTime) {
            return true;
        }

        return false;
    }

    /**
     * @param string $sourceImageFilename
     * @param string $destinationImageFilename
     * @return bool
     * @throws NotFoundException
     */
    public function needsConversion(string $sourceImageFilename, string $destinationImageFilename): bool
    {
        if ($this->fileExists($sourceImageFilename) === false) {
            return false;
        }

        if ($this->fileExists($destinationImageFilename)) {
            return false;
        }

        if ($this->isNewerThan($destinationImageFilename, $sourceImageFilename)) {
            return false;
        }

        return true;
    }

    /**
     * @param string $uri
     * @return bool
     * @throws ConvertorException
     * @deprecated Use uriExists($uri) instead
     */
    public function urlExists(string $uri): bool
    {
        return $this->uriExists($uri);
    }
}
