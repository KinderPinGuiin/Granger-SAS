<?php

namespace App\Utils;

use Exception;
use Google\Client;

use function PHPUnit\Framework\callback;

/**
 * A simple Google Drive manager able to list / create / update / delete folders 
 * and files
 */
class GoogleDriveManager {

    /**
     * @var Client
     */
    private $client;

    /**
     * Drive service 
     * 
     * @var \Google_Service_Drive
     */
    private $service;

    /**
     * Current folder stack for relativeList() / goTo() / goToName() / back()
     * 
     * @var array
     */
    private $folderStack = [];

    /**
     * Research filters
     * 
     * @see https://developers.google.com/drive/api/v3/mime-types
     */
    public const FILTER = [
        "audio"              => "application/vnd.google-apps.audio",
        "doc"                => "application/vnd.google-apps.document",
        "3rd_party_shortcut" => "application/vnd.google-apps.drive-sdk",
        "draw"               => "application/vnd.google-apps.drawing",
        "file"               => "application/vnd.google-apps.file",
        "folder"             => "application/vnd.google-apps.folder",
        "form"               => "application/vnd.google-apps.form",
        "fusion_table"       => "application/vnd.google-apps.fusiontable",
        "map"                => "application/vnd.google-apps.map",
        "photo"              => "application/vnd.google-apps.photo",
        "slide"              => "application/vnd.google-apps.presentation",
        "script"             => "application/vnd.google-apps.script",
        "shortcut"           => "application/vnd.google-apps.shortcut",
        "site"               => "application/vnd.google-apps.site",
        "sheet"              => "application/vnd.google-apps.spreadsheet",
        "unknown"            => "application/vnd.google-apps.unknown",
        "video"              => "application/vnd.google-apps.video"
    ];

    /**
     * Use the $credentialsPath to establish a connection to the drive account
     * into the specified root folder
     * 
     * @param string $credentialsPath A path to the credentials.json
     * @param string $root            Folder root where start list
     * @param string $scope           The scope
     */
    public function __construct(string $credentialsPath, string $root, string $scope = \Google_Service_Drive::DRIVE)
    {
        if (!file_exists($credentialsPath)) {
            throw new Exception("Invalid credentials path : $credentialsPath");
        }
        putenv('GOOGLE_APPLICATION_CREDENTIALS=' . $credentialsPath);
        // Establish connection to API
        $this->client = new Client();
        $this->client->useApplicationDefaultCredentials();
        $this->client->addScope($scope);
        // Set the current folder
        $this->folderStack[] = $root;
        // Start the Drive service
        $this->service = new \Google_Service_Drive($this->client);
    }

    /**
     * Defines wether the connection to the drive should be secure or not.
     * Default is true
     * 
     * @param boolean $value True if yes or false else
     */
    public function securize(bool $value)
    {
        $this->client->setHttpClient(
            new \GuzzleHttp\Client(['verify' => $value])
        );
    }

    /**
     * List files
     *
     * @param mixed $filter  A filter to only show what a part of files 
     *                       (cf. FILTER[])
     * @param array $options Options to apply to the research
     * @see https://developers.google.com/drive/api/v3/reference/files/list#parameters
     * @see https://developers.google.com/drive/api/v3/ref-search-terms
     */
    public function list($filter = [], array $options = []): \Google_Service_Drive_FileList
    {
        $options = $this->buildOptions(
            (is_array($filter) ? $filter : [$filter]), $options
        );

        return $this->service->files->listFiles($options);
    }

    /**
     * List files relative to getCurrentFolder()
     * 
     * @param mixed $filter  A filter to only show what a part of files (cf. FILTER[])
     * @param array $options Options to apply to the research
     * @see https://developers.google.com/drive/api/v3/reference/files/list#parameters
     * @see https://developers.google.com/drive/api/v3/ref-search-terms
     */
    public function relativeList($filter = [], array $options = []): \Google_Service_Drive_FileList
    {
        $options = $this->buildOptions(
            (is_array($filter) ? $filter : [$filter]), $options
        );
        // If parent is set we replace it
        $currentFolder = $this->getCurrentFolder();
        if (!empty($options["q"]) 
          && preg_match("#'(.+)' in parents#i", $options["q"], $matches)) {
            str_replace(
                $matches[0], "'$currentFolder' in parents'", $options["q"]
            );
        } else if (!empty($options["q"])) {
            $options["q"] .= " and '$currentFolder' in parents";
        } else {
            $options["q"] = "'$currentFolder' in parents";
        }

        return $this->service->files->listFiles($options);
    }

    /**
     * Apply a callback to each files in the folder getCurrentFolder()
     * 
     * @param  callback $callback   Callback to apply for each files
     * @param  mixed    $acc        An accumulator for callback
     * @param  bool     $mapFolders True if you want to apply function to 
     *                              folders or false otherwise
     * 
     * @return mixed                The accumulator or null if it's not set
     */
    public function mapFiles(callable $callback, $acc = null, bool $mapFolders = false)
    {
        $files = $this->relativeList()["files"];
        foreach ($files as $file) {
            if ($mapFolders || $file["mimeType"] !== self::FILTER["folder"]) {
                $acc = $callback($file, $acc);
            }
        }

        return $acc;
    }

    /**
     * Return true if file exists in getCurrentFolder() or false otherwise
     * 
     * @param  string   $name         File name or regex
     * @param  bool     $checkFolders True if you want to apply this function to
     *                                folders or false otherwise
     * @return bool
     */
    public function fileExists($name, $checkFolders = false): bool
    {
        return $this->mapFiles(function ($file) use ($name) {
            if (preg_match("#" . $name . "#", $file["name"])) {
                return true;
            }
            return false;
        }, false, $checkFolders);
    }

    /**
     * Renvoie true si le fichier passé en paramètre est un dossier et false 
     * sinon
     * 
     * @return bool
     */
    public function isFolder(\Google_Service_Drive_DriveFile $file): bool
    {
        return $file["mimeType"] === self::FILTER["folder"];
    }

    /**
     * Define the new current folder
     * 
     * @param  string $id ID of the new folder
     * @return bool       True if the folder exists and false otherwise
     */
    public function goTo(string $id) 
    {
        $folders = $this->relativeList("folder");
        // Check if folder exist
        $found = false;
        foreach ($folders["files"] as $folder) {
            // If yes we can add it to folder stack
            if ($folder["id"] === $id) {
                $this->folderStack[] = $id;
                $found = true;
                break;
            }
        }

        return $found;
    }

    /**
     * Define the new current folder
     * 
     * @param  string $name Folder name
     * @return bool         True if the folder exists and false otherwise 
     */
    public function goToName(string $name)
    {
        $id = $this->getID($name);
        if (empty($id)) {
            return false;
        }
        return $this->goTo($id);
    }

    /**
     * Back to the previous folder
     */
    public function back()
    {
        if (count($this->folderStack) > 1) {
            array_pop($this->folderStack);
        } else {
            throw new Exception("Can't back anymore");
        }
    }

    /**
     * Update a file thanks to his ID
     * 
     * @param string $id      File ID
     * @param array  $options Options about update
     * @param string $content File content (null mean no changes). You can use
     *                        file_get_contents()
     * @return Google_Service_Drive_DriveFile The file updated
     * @see https://developers.google.com/drive/api/v2/reference/files/update
     */
    public function update($id, $options = [], $content = null): \Google_Service_Drive_DriveFile
    {
        // Create a new file
        $driveFile = new \Google_Service_Drive_DriveFile();
        // Applies options by calling good functions
        $this->applyOptions($driveFile, $options);
        // Add content
        $optParams = [];
        if ($content !== null) {
            $optParams["data"] = $content;
            if (isset($options["mimeType"]) && !empty($options["mimeType"])) {
                $optParams["mimeType"] = $options["mimeType"];
            }
        }

        return $this->service->files->update($id, $driveFile, $optParams);
    }

    /**
     * Update a file thanks to his name. The file must be accessible by
     * relativeList()
     * 
     * @param string $name    File name
     * @param array  $options Options about update
     * @param string $content File content (null mean no changes). You can use
     *                        file_get_contents()
     * @return Google_Service_Drive_DriveFile The file updated
     * @see https://developers.google.com/drive/api/v2/reference/files/update
     */
    public function updateByName($name, $options = [], $content = null): \Google_Service_Drive_DriveFile
    {
        // Get the file ID
        $id = $this->getID($name);
        if (empty($id)) {
            throw new Exception("File $name not found");
        }

        return $this->update($id, $options, $content);
    }

    /**
     * Upload file
     * 
     * @param string $fileName   The file name
     * @param string $filePath   The current file path, if null it'll create an 
     *                           empty file
     * @param array  $options    Files options
     * @param string $parentId   Parent of the new folder, if null it'll be 
     *                           create in the getCurrentFolder() folder
     * @return Google_Service_Drive_DriveFile The created folder
     * @see
     */
    public function upload(string $fileName, string $filePath = null, array $options = [], string $parentId = null): \Google_Service_Drive_DriveFile
    {
        // Create the new file
        $driveFile = new \Google_Service_Drive_DriveFile();
        // Apply options and set name
        $this->applyOptions($driveFile, $options);
        $driveFile->setName($fileName);
        // Define the file parent if it's not set
        if ($parentId === null) {
            $parentId = $this->getCurrentFolder();
        }
        $driveFile->setParents([$parentId]);
        // Add file content
        $optParams = [];
        if ($filePath !== null) {
            $optParams["data"] = file_get_contents($filePath);
            if (isset($options["mimeType"]) && !empty($options["mimeType"])) {
                $optParams["mimeType"] = $options["mimeType"];
            }
        }
        // Upload file
        $result = $this->service->files->create($driveFile, $optParams);
        // Check if file is uploaded
        if (!isset($result["name"]) || empty($result["name"])) {
            throw new Exception("File not uploaded");
        }

        return $result;
    }

    /**
     * Create a folder
     * 
     * @param string $folderName The folder name
     * @param string $parentId   Parent of the new folder, if null it'll be 
     *                           create in the getCurrentFolder() folder
     * @return Google_Service_Drive_DriveFile The created folder
     */
    public function createFolder(string $folderName, string $parentId = null): \Google_Service_Drive_DriveFile
    {
        return $this->upload($folderName, null, [
            "mimeType" => self::FILTER["folder"]
        ], $parentId);
    }

    /**
     * Delete a file or folder thanks to his ID 
     * 
     * @param string $fileId File ID
     * @see https://developers.google.com/drive/api/v2/reference/files/delete
     */
    public function delete(string $fileId)
    {
        $this->service->files->delete($fileId);
    }

    /**
     * Delete a file or folder thanks to his name. The file must be accessible
     * by relativeList()
     * 
     * @param string $fileName File name
     * @see https://developers.google.com/drive/api/v2/reference/files/delete
     */
    public function deleteByName(string $fileName)
    {
        // Get the file ID
        $id = $this->getID($fileName);
        if (empty($id)) {
            throw new Exception("File $fileName not found");
        }

        $this->delete($id);
    }

    /**
     * Getters / Setters
     */

    public function getClient(): Client
    {
        return $this->client;
    }

    public function getService(): \Google_Service_Drive
    {
        return $this->service;
    }

    public function getFolderStack(): array
    {
        return $this->folderStack;
    }

    /**
     * Clear the folder stack
     */
    public function clearFolderStack()
    {
        $this->folderStack = [$this->folderStack[0]];
    }

    public function getCurrentFolder(): string
    {
        return $this->folderStack[count($this->folderStack) - 1];
    }

    /**
     * Private methods
     */

    /**
     * Merge filters and options to build real request options
     * 
     * @param  array $filters Filters to use to determine MIME
     * @param  array $options Request options
     * @return array          Merged options
     */
    private function buildOptions(array $filters, array $options): array
    {
        if (empty($filters) || $filters[0] === "") {
            return $options;
        }
        // Build the MIME query
        $mimeQuery = "";
        foreach ($filters as $filter) {
            if (!isset(self::FILTER[$filter])) {
                throw new Exception("Invalid filter : $filter");
            }
            $mimeQuery .= "mimeType='" . self::FILTER[$filter] . "' or ";
        }
        $mimeQuery = trim($mimeQuery, " or ");
        // Add it to the final query
        if (!empty($options["q"]) && $mimeQuery !== "") {
            $options["q"] .= " and $mimeQuery";
        } else if (empty($options["q"])) {
            $options["q"] = $mimeQuery;
        }

        return $options;
    }

    /**
     * Return the ID of a file thanks to his name. This method will search on
     * the relativeList() files list
     * 
     * @param string $name Name of the new folder
     * @return mixed The ID of the file or null if it's not found
     */
    private function getID(string $name)
    {
        $folders = $this->relativeList();
        $id = null;
        foreach ($folders["files"] as $folder) {
            if (preg_match("#" . $name . "#", $folder["name"]) === 1) {
                $id = $folder["id"];
                break;
            }
        }
        return $id;
    }

    /**
     * Apply options to a file
     * 
     * @param Google_Service_Drive_DriveFile $driveFile The file
     * @param array                          $options   Les options
     */
    private function applyOptions(\Google_Service_Drive_DriveFile $driveFile, array $options)
    {
        $func = "";
        foreach ($options as $optionName => $value) {
            $func = "set" . ucfirst($optionName);
            if (method_exists($driveFile, $func)) {
                call_user_func_array(
                    [$driveFile, $func], [$value]
                );
            } else {
                throw new Exception("Invalid option $optionName");
            }
        }
    }

}