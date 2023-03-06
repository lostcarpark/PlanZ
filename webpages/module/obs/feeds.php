<?php

/**
 * Extract OPS feeds.
 *
 * PHP version 7.1+
 *
 * @category Module
 * @package  PlanZ
 * @author   James Shields <james@lostcarpark.com>
 * @license  Zambia Software Licence
 * @link     https://github.com/LVerhulst4321/PlanZ
 */

$title = "Staff - Create OBS Feeds";
require_once __DIR__ . '/../../db_functions.php';
require_once __DIR__ . '/../../error_functions.php';
require_once __DIR__ . '/../../render_functions.php';
require_once __DIR__ . '/../../StaffCommonCode.php';
require_once 'obs_functions.php';

staff_header($title, true);

showObsFeedOptions();

staff_footer();

/**
 * Show options for extracting the OBS feeds.
 *
 * @return void
 */
function showObsFeedOptions(): void
{
    // Check we're allowed to do this.
    if (!(isLoggedIn() && may_I("Administrator"))) {
        echo ("<h2>Insufficient privilege.</h2>");
        return;
    }

        // Check extract directory set.
    if (!defined("OBS_EXTRACT_DIRECTORY")) {
        echo <<<EOD
            <h2>Extract directory not defined</h2>
            <p>
                The setting OBS_EXTRACT_DIRECTORY is not defined.
                Use the <a href="ConfigurationAdmin.php">configuration editor</a>
                to set value of OBS_EXTRACT_DIRECTORY, then save.
            </p>
        EOD;
        return;
    }
    // Check extract directory exists.
    $directory = __DIR__ . '/../../' . OBS_EXTRACT_DIRECTORY;
    if (!file_exists($directory) || !is_dir($directory)) {
        echo <<<EOD
            <h2>Directory $directory not found</h2>
            <p>Attempting to create.</p>
        EOD;
        try {
            mkdir(__DIR__ . '/' . OBS_EXTRACT_DIRECTORY);
        }
        catch (Exception $e) {
            echo <<<EOD
                <p>Unable to create directory.
                Please create manually and ensure writable.</p>
            EOD;
            return;
        }
        echo "<p>Directory $directory created.</p>";
    }
    if (!is_writable($directory)) {
        echo <<<EOD
            <h2>Directory not writable</h2>
            <p>Please ensure write permission on directory $directory.</p>
        EOD;
        return;
    }
    $query = <<<EOD
        SELECT roomid, roomname
        FROM Rooms;
    EOD;
    // If we get here, directory should be good, so let's create some files...
    echo <<<EOD
        <h2>Writing OBS files...</h2>
        <p>Writing files per room.</p>
    EOD;
    // Get participants.
    $participants = getParticipants();
    $rootDir = realpath(__DIR__ . '/../../');
    $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off'
        ? 'https' : 'http')
        . '://'
        . $_SERVER['SERVER_NAME'];
    $result = mysqli_query_with_error_handling($query);
    echo "<ul>";
    while ($row = mysqli_fetch_object($result)) {
        $filename = makeRoomFileName($row->roomname);
        $filepath = OBS_EXTRACT_DIRECTORY . '/' . $filename;
        writeObsRoomFile($row->roomid, $rootDir . '/' . $filepath, $participants);
        echo "<li><a href=\"/$filepath\">$baseUrl/$filepath</a></li>";
    }
    echo "</ul>";
}

/**
 * Take out non-alphanumberic chars.
 *
 * @param string $name The room name to make into a filename.
 *
 * @return string
 */
function makeRoomFileName(string $name): string
{
    return 'obs_' . preg_replace('/[^a-zA-Z0-9]/', '', $name) . '.json';
}
