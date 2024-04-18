<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Nolej API
 *
 * @package     local_nolej
 * @author      2023 Vincenzo Padula <vincenzo@oc-group.eu>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_nolej\api;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/nolej/classes/event/webhook_called.php');

class api
{
    const STATUS_CREATION = 0;
    const STATUS_CREATION_PENDING = 1;
    const STATUS_ANALYSIS = 2;
    const STATUS_ANALYSIS_PENDING = 3;
    const STATUS_REVISION = 4;
    const STATUS_REVISION_PENDING = 5;
    const STATUS_ACTIVITIES = 6;
    const STATUS_ACTIVITIES_PENDING = 7;
    const STATUS_COMPLETED = 8;
    const STATUS_FAILED = 9;

    const TYPE_AUDIO = ['mp3', 'wav', 'opus', 'ogg', 'oga', 'm4a'];
    const TYPE_VIDEO = ['m4v', 'mp4', 'ogv', 'avi', 'webm'];
    const TYPE_DOC = ['pdf', 'doc', 'docx', 'odt'];
    const TYPE_TEXT = ['txt', 'htm', 'html'];

    const API_URL = 'https://api-live.nolej.io';

    /** @var array */
    protected $data;

    /** @var bool */
    protected $shouldDie = false;

    /**
     * Check that the API key has been set
     * @return bool
     */
    public static function haskey()
    {
        return !empty(get_config('local_nolej', 'api_key'));
    }

    /**
     * Send a GET request to Nolej API
     * @param string $path
     * @param array $data
     * @param bool $encodeinput
     * @param bool $decodeoutput
     *
     * @return object|string return the result given by Nolej. If
     * $decodeoutput is true, treat the result as json object and decode it.
     */
    public static function get(
        $path,
        $data = [],
        $encodeinput = false,
        $decodeoutput = true
    ) {
        $apikey = get_config('local_nolej', 'api_key');
        if (!$apikey) {
            return null;
        }

        $url = self::API_URL . $path;
        $encodeddata = empty($data) ? null : ($encodeinput ? json_encode($data) : $data);

        $options = array(
            'CURLOPT_CUSTOMREQUEST' => 'GET', // Need a GET request with POST data
            'RETURNTRANSFER' => 1,
            'HEADER' => 0,
            'FAILONERROR' => 0
        );

        $header = array(
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: X-API-KEY ' . $apikey,
            'User-Agent: Moodle Plugin'
        );

        $curl = new \curl();
        $curl->setHeader($header);
        $response = $curl->post($url, $encodeddata, $options);

        if (!$decodeoutput) {
            return $response;
        }

        $object = json_decode($response);
        return $object !== null ? $object : $response;
    }

    /**
     * Send a POST request to Nolej API
     * @param string $path
     * @param array $data
     * @param bool $decode
     *
     * @return object|string return the result given by Nolej. If
     * $decodeOutput is true, treat the result as json object and decode it.
     */
    public static function post(
        $path,
        $data = array(),
        $decode = true
    ) {
        $apikey = get_config('local_nolej', 'api_key');
        if (!$apikey) {
            return null;
        }

        $jsondata = json_encode($data);
        $url = self::API_URL . $path;

        $options = array(
            'RETURNTRANSFER' => 1,
            'HEADER' => 0,
            'FAILONERROR' => 0
        );

        $header = array(
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: X-API-KEY ' . $apikey,
            'User-Agent: Moodle Plugin'
        );

        $curl = new \curl();
        $curl->setHeader($header);
        $jsonresult = $curl->post($url, $jsondata, $options);

        if (!$decode) {
            return $jsonresult;
        }

        $object = json_decode($jsonresult);
        return $object !== null ? $object : $jsonresult;
    }

    /**
     * Send a PUT request to Nolej API
     * @param string $path
     * @param mixed $data
     * @param bool $encode input's data
     * @param bool $decode output
     */
    public static function put(
        $path,
        $data = array(),
        $encode = false,
        $decode = true
    ) {
        $apikey = get_config('local_nolej', 'api_key');
        if (!$apikey) {
            return null;
        }

        // TODO: send a PUT request
        $jsondata = $encode ? json_encode($data) : $data;
        $url = self::API_URL . $path;

        $options = array(
            'RETURNTRANSFER' => 1,
            'HEADER' => 0,
            'FAILONERROR' => 0
        );

        $header = array(
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: X-API-KEY ' . $apikey,
            'User-Agent: Moodle Plugin'
        );

        $curl = new \curl();
        $curl->setHeader($header);
        $jsonresult = $curl->put($url, $jsondata, $options);

        if (!$decode) {
            return $jsonresult;
        }

        $object = json_decode($jsonresult);
        return $object !== null ? $object : $jsonresult;
    }

    /**
     * @return array
     */
    public static function allowedtypes()
    {
        return array_merge(
            self::TYPE_AUDIO,
            self::TYPE_VIDEO,
            self::TYPE_DOC,
            self::TYPE_TEXT
        );
    }

    /**
     * Return the format given the extension, or empty string if not valid.
     * @param string $extension
     * @return string
     */
    public static function formatfromextension($extension)
    {
        if (in_array($extension, self::TYPE_AUDIO)) {
            return 'audio';
        }
        if (in_array($extension, self::TYPE_VIDEO)) {
            return 'video';
        }
        if (in_array($extension, self::TYPE_DOC)) {
            return 'document';
        }
        if (in_array($extension, self::TYPE_TEXT)) {
            return 'freetext';
        }
        return '';
    }

    /**
     * Return the Nolej data directory
     * @param ?string $documentid (optional)
     * @return string
     */
    public static function datadir($documentid = null)
    {
        global $CFG;
        $datadir = $CFG->dataroot . '/local_nolej';
        if ($documentid != null) {
            $datadir .= '/' . $documentid;
        }
        if (!file_exists($datadir)) {
            mkdir($datadir, 0777, true);
        }
        return $datadir;
    }

    /**
     * Return the Nolej upload directory
     * @return string
     */
    public static function uploaddir()
    {
        $datadir = self::datadir();
        $uploaddir = $datadir . '/uploads';
        if (!file_exists($uploaddir)) {
            mkdir($uploaddir, 0777, true);
        }
        return $uploaddir;
    }

    /**
     * Return the Nolej h5p directory for a document
     * @param string $documentid
     * @return string
     */
    public static function h5pdir($documentid)
    {
        $datadir = self::datadir($documentid);
        $uploaddir = $datadir . '/h5p';
        if (!file_exists($uploaddir)) {
            mkdir($uploaddir, 0777, true);
        }
        return $uploaddir;
    }

    /**
     * Fetch and save the content of a Nolej file
     *
     * @param string $documentid
     * @param string $pathname the 'id' of Nolej file
     * @param string|null $saveas the name of the file to be saved as
     * @param bool $forcedownload if false check if the file already exists
     * @param array $withdata
     * @param bool $encodeinput iff true encode input's data
     *
     * @return bool|string return true on success, false on failure. If $saveAs
     * is null, then the content is returned as string.
     */
    public static function getcontent(
        $documentid,
        $pathname,
        $saveas = null,
        $forcedownload = false,
        $withdata = [],
        $encodeinput = false
    ) {
        $filepath = self::datadir($documentid) . '/' . $saveas;

        if (
            $saveas != null &&
            !$forcedownload &&
            is_file($filepath)
        ) {
            return true;
        }

        $result = self::get(
            sprintf('/documents/%s/%s', $documentid, $pathname),
            $withdata,
            $encodeinput,
            false
        );

        return $saveas == null
            ? $result
            : self::writecontent($documentid, $saveas, $result);
    }

    /**
     * Read a file of the requested document, if exists.
     *
     * @param string $documentid
     * @param string $filename the name of the file to read
     * @return string|false return the content if the file exists,
     * false otherwise.
     */
    public static function readcontent($documentid, $filename)
    {
        $path = self::datadir($documentid) . '/' . $filename;
        if (!file_exists($path)) {
            return false;
        }
        return file_get_contents($path);
    }

    /**
     * Put the content of a file to Nolej
     *
     * @param string $documentid
     * @param string $pathname the 'id' of Nolej file
     * @param string $filename the name of the file on disk
     *
     * @return bool true on success, false on failure
     */
    public static function putcontent($documentid, $pathname, $filename)
    {
        $content = self::readcontent($documentid, $filename);
        if (!$content) {
            return false;
        }

        return self::put(
            sprintf('/documents/%s/%s', $documentid, $pathname),
            $content
        );
    }

    /**
     * Write a file of the document.
     *
     * @param string $documentid
     * @param string $filename the name of the file.
     * @param string $content the content of the file.
     *
     * @return bool returns true on success, false on failure.
     */
    public static function writecontent($documentid, $filename, $content)
    {
        return file_put_contents(
            self::datadir($documentid) . '/' . $filename,
            $content
        ) !== false;
    }

    /**
     * Lookup for a document status
     * @param string $documentid
     * @param int $userid (optional)
     * @return int status
     */
    public static function lookupdocumentstatus($documentid, $userid = null)
    {
        global $DB;

        if ($userid != null) {
            $document = $DB->get_record(
                'nolej_module',
                [
                    'document_id' => $documentid,
                    'user_id' => $userid
                ]
            );
        } else {
            $document = $DB->get_record(
                'nolej_module',
                ['document_id' => $documentid]
            );
        }

        if (!$document) {
            return -1;
        }

        return $document->status;
    }

    /**
     * @param string $msg
     * @param ?string $documentid
     */
    public function log($msg, $documentid = null)
    {
        // TODO
        $event = \local_nolej\event\webhook_called::create(
            [
                'context' => \context_system::instance(),
                'other' => [
                    'documentid' => $documentid,
                    'message' => $msg
                ]
            ]
        );
        $event->trigger();
    }

    /**
     * Parse the request from POST content if
     * @param mixed $data is not null
     */
    public function parse($data = null)
    {
        if ($data == null) {
            header('Content-type: application/json; charset=UTF-8');
            $data = json_decode(file_get_contents('php://input'), true);
            $this->shouldDie = true;
        }

        if (
            !is_array($data) ||
            !isset($data['action']) ||
            !is_string($data['action'])
        ) {
            $this->diemessage(400, 'Request not valid.');
            $this->log('Received invalid request: ' . var_export($data, true));
        }

        $this->data = $data;
        switch ($data['action']) {
            case 'transcription':
                $this->log('Received transcription request: ' . var_export($data, true));
                $this->checktranscription();
                break;

            case 'analysis':
                $this->log('Received analysis request: ' . var_export($data, true));
                $this->checkanalysis();
                break;

            case 'activities':
                $this->log('Received activities request: ' . var_export($data, true));
                $this->checkactivities();
                break;

            case 'work in progress':
                $this->log('Received work in progress.');
                if (isloggedin() && !isguestuser()) {
                    \core\notification::add(get_string('work_in_progress', 'local_nolej'), \core\output\notification::NOTIFY_INFO);
                    return;
                }
                break;

            default:
                $this->log('Received invalid action: ' . var_export($data, true));
        }
    }

    /**
     * Die with status code and a message
     * @param int $code
     * @param string $message
     */
    protected function diemessage(
        $code = 400,
        $message = ''
    ) {
        if (!empty($message)) {
            $this->log('Replied to Nolej with message: ' . $message);
            if ($this->shouldDie) {
                echo json_encode(['message' => $message]);
            }
        }

        if (!$this->shouldDie) {
            return false;
        }

        http_response_code($code);
        exit;
    }

    /**
     * Look for a document with a specific status
     * @param string $documentid
     * @param int $status
     * @return object|false
     */
    public function lookupdocumentwithstatus($documentid, $status)
    {
        global $DB;

        return $DB->get_record(
            'nolej_module',
            [
                'document_id' => $documentid,
                'status' => $status
            ]
        );
    }

    public function checktranscription()
    {
        global $DB;

        if ($this->data['consumedCredit'] == null) {
            $this->data['consumedCredit'] = 0;
        }

        if (
            !isset(
                $this->data['documentID'],
                $this->data['status'],
                $this->data['code'],
                $this->data['error_message'],
                $this->data['consumedCredit']
            ) ||
            !is_string($this->data['documentID']) ||
            !is_string($this->data['status']) ||
            !is_string($this->data['error_message']) ||
            !is_integer($this->data['code']) ||
            !is_integer($this->data['consumedCredit'])
        ) {
            $this->diemessage(400, 'Request not valid.');
            return;
        }

        $documentid = $this->data['documentID'];

        $document = $this->lookupdocumentwithstatus($documentid, self::STATUS_CREATION_PENDING);
        if (!$document) {
            $this->diemessage(404, 'Document ID not found.');
            return;
        }

        $this->setLanguageOfUser((int) $document->user_id);

        $now = time();
        // $this->setUserLang($document['user_id']);

        if (
            $this->data['status'] != '\'ok\'' &&
            $this->data['status'] != 'ok'
        ) {
            $this->log('Result: ko');

            $success = $DB->update_record(
                'nolej_module',
                (object) [
                    'id' => $document->id,
                    'document_id' => $documentid,
                    'status' => self::STATUS_FAILED,
                    'consumed_credit' => $this->data['consumedCredit']
                ]
            );
            if (!$success) {
                $this->diemessage(404, 'Document not found.');
            }

            $this->sendnotification(
                $documentid,
                (int) $document->user_id,
                'transcription_ko',
                $this->data['status'],
                $this->data['code'],
                $this->data['error_message'],
                $this->data['consumedCredit'],
                'action_transcription_ko_body',
                (object) [
                    'title' => $document->title,
                    'tstamp' => userdate($now, get_string('strftimedatetimeshortaccurate', 'core_langconfig')),
                    'errormessage' => $this->data['error_message']
                ]
            );
            return;
        }

        $success = $DB->update_record(
            'nolej_module',
            (object) [
                'id' => $document->id,
                'document_id' => $documentid,
                'status' => self::STATUS_ANALYSIS,
                'consumed_credit' => $this->data['consumedCredit']
            ]
        );
        if (!$success) {
            $this->diemessage(404, 'Document not found.');
        }

        $this->sendnotification(
            $documentid,
            (int) $document->user_id,
            'transcription_ok',
            $this->data['status'],
            $this->data['code'],
            $this->data['error_message'],
            $this->data['consumedCredit'],
            'action_transcription_ok_body',
            (object) [
                'title' => $document->title,
                'tstamp' => userdate($now, get_string('strftimedatetimeshortaccurate', 'core_langconfig'))
            ]
        );

        $this->diemessage(200, 'Transcription received!');
    }

    public function checkanalysis()
    {
        global $DB;

        if ($this->data['consumedCredit'] == null) {
            $this->data['consumedCredit'] = 0;
        }

        if (
            !isset(
                $this->data['documentID'],
                $this->data['status'],
                $this->data['code'],
                $this->data['error_message'],
                $this->data['consumedCredit']
            ) ||
            !is_string($this->data['documentID']) ||
            !is_string($this->data['status']) ||
            !is_string($this->data['error_message']) ||
            !is_integer($this->data['code']) ||
            !is_integer($this->data['consumedCredit'])
        ) {
            $this->diemessage(400, 'Request not valid.');
            return;
        }

        $documentid = $this->data['documentID'];

        $document = $this->lookupdocumentwithstatus($documentid, self::STATUS_ANALYSIS_PENDING);
        if (!$document) {
            $this->diemessage(404, 'Document ID not found.');
            return;
        }

        $this->setLanguageOfUser((int) $document->user_id);

        $now = time();
        // $this->setUserLang($document['user_id']);

        if (
            $this->data['status'] != '\'ok\'' &&
            $this->data['status'] != 'ok'
        ) {
            $this->log('Result: ko');

            $success = $DB->update_record(
                'nolej_module',
                (object) [
                    'id' => $document->id,
                    'document_id' => $documentid,
                    'status' => self::STATUS_FAILED,
                    'consumed_credit' => $this->data['consumedCredit']
                ]
            );
            if (!$success) {
                $this->diemessage(404, 'Document not found.');
            }

            $this->sendnotification(
                $documentid,
                (int) $document->user_id,
                'analysis_ko',
                $this->data['status'],
                $this->data['code'],
                $this->data['error_message'],
                $this->data['consumedCredit'],
                'action_analysis_ko_body',
                (object) [
                    'title' => $document->title,
                    'tstamp' => userdate($now, get_string('strftimedatetimeshortaccurate', 'core_langconfig')),
                    'errormessage' => $this->data['error_message']
                ]
            );
            return;
        }

        $success = $DB->update_record(
            'nolej_module',
            (object) [
                'id' => $document->id,
                'document_id' => $documentid,
                'status' => self::STATUS_REVISION,
                'consumed_credit' => $this->data['consumedCredit']
            ]
        );
        if (!$success) {
            $this->diemessage(404, 'Document not found.');
        }

        $this->sendnotification(
            $documentid,
            (int) $document->user_id,
            'analysis_ok',
            $this->data['status'],
            $this->data['code'],
            $this->data['error_message'],
            $this->data['consumedCredit'],
            'action_analysis_ok_body',
            (object) [
                'title' => $document->title,
                'tstamp' => userdate($now, get_string('strftimedatetimeshortaccurate', 'core_langconfig'))
            ]
        );

        $this->diemessage(200, 'Analysis received!');
    }

    function checkactivities()
    {
        global $DB;

        if ($this->data['consumedCredit'] == null) {
            $this->data['consumedCredit'] = 0;
        }

        if (
            !isset(
                $this->data['documentID'],
                $this->data['status'],
                $this->data['code'],
                $this->data['error_message'],
                $this->data['consumedCredit']
            ) ||
            !is_string($this->data['documentID']) ||
            !is_string($this->data['status']) ||
            !is_string($this->data['error_message']) ||
            !is_integer($this->data['code']) ||
            !is_integer($this->data['consumedCredit'])
        ) {
            $this->diemessage(400, 'Request not valid.');
            return;
        }

        $documentid = $this->data['documentID'];

        $document = $this->lookupdocumentwithstatus($documentid, self::STATUS_ACTIVITIES_PENDING);
        if (!$document) {
            $this->diemessage(404, 'Document ID not found.');
            return;
        }

        $this->setLanguageOfUser((int) $document->user_id);

        $now = time();
        // $this->setUserLang($document['user_id']);

        if (
            $this->data['status'] != '\'ok\'' &&
            $this->data['status'] != 'ok'
        ) {
            $this->log('Result: ko');

            $success = $DB->update_record(
                'nolej_module',
                (object) [
                    'id' => $document->id,
                    'document_id' => $documentid,
                    'status' => self::STATUS_ACTIVITIES,
                    'consumed_credit' => $this->data['consumedCredit']
                ]
            );
            if (!$success) {
                $this->diemessage(404, 'Document not found.');
            }

            $this->sendnotification(
                $documentid,
                (int) $document->user_id,
                'activities_ko',
                $this->data['status'],
                $this->data['code'],
                $this->data['error_message'],
                $this->data['consumedCredit'],
                'action_activities_ko_body',
                (object) [
                    'title' => $document->title,
                    'tstamp' => userdate($now, get_string('strftimedatetimeshortaccurate', 'core_langconfig')),
                    'errormessage' => $this->data['error_message']
                ]
            );
            return;
        }

        $success = $DB->update_record(
            'nolej_module',
            (object) [
                'id' => $document->id,
                'document_id' => $documentid,
                'status' => self::STATUS_COMPLETED,
                'consumed_credit' => $this->data['consumedCredit']
            ]
        );
        if (!$success) {
            $this->diemessage(404, 'Document not found.');
        }

        $errors = $this->downloadactivities($document);
        if (!empty($errors)) {
            $this->log('Failed to download some activities: ' . $errors . '.');
            $this->sendnotification(
                $documentid,
                (int) $document->user_id,
                'activities_ko',
                $this->data['status'],
                $this->data['code'],
                $this->data['error_message'],
                $this->data['consumedCredit'],
                'err_activities_get',
                (object) [
                    'errors' => '<ul><li>' . join('</li><li>', $errors) . '</li></ul>'
                ]
            );

            $this->diemessage(200, 'Activities received, but something went wrong while retrieving them.');
            return;
        }

        $this->sendnotification(
            $documentid,
            (int) $document->user_id,
            'activities_ok',
            $this->data['status'],
            $this->data['code'],
            $this->data['error_message'],
            $this->data['consumedCredit'],
            'action_activities_ok_body',
            (object) [
                'title' => $document->title,
                'tstamp' => userdate($now, get_string('strftimedatetimeshortaccurate', 'core_langconfig'))
            ]
        );

        $this->diemessage(200, 'Activities received!');
    }

    /**
     * Get the Nolej category id,
     * create it if not exists.
     * @return int
     */
    protected static function getnolejcategoryid()
    {
        $categoryid = get_config('local_nolej', 'categoryid');

        if (!empty($categoryid) && \core_course_category::get($categoryid, IGNORE_MISSING, true) != null) {
            return (int) $categoryid;
        }

        // Create context if not exists
        $nolejcategory = \core_course_category::create((object) [
            'name' => 'Nolej',
            'description' => 'This category contains Nolej h5p contents',
            'parent' => 0,
            'visible' => 0
        ]);
        $categoryid = $nolejcategory->id;
        set_config('categoryid', $categoryid, 'local_nolej');
        return (int) $categoryid;
    }

    /**
     * Download activities and save them in the Content Box
     * 
     * @param object $document
     * @return array of errors
     */
    public function downloadactivities($document)
    {
        global $CFG, $DB;

        $errors = [];

        $nolejcategoryid = self::getnolejcategoryid();
        $h5pdir = self::h5pdir($document->document_id);
        $now = time();

        $fs = get_file_storage();
        $h5pfactory = new \core_h5p\factory();

        $json = self::getcontent(
            $document->document_id,
            'activities',
            null,
            true,
            ['format' => 'h5p'],
            true
        );
        if (!$json) {
            return get_string('erractivitiesdecode', 'local_nolej');
        }
        $activities = json_decode($json);
        $activities = $activities->activities;

        // Create category
        $modulecategory = \core_course_category::create((object) [
            'name' => sprintf('%s (%s)', $document->title, userdate($now, get_string('strftimedatetimeshortaccurate', 'core_langconfig'))),
            'description' => userdate($now, get_string('strftimedatetimeshortaccurate', 'core_langconfig')),
            'parent' => $nolejcategoryid
        ]);
        $modulecontext = \context_coursecat::instance($modulecategory->id);

        // $enrol = enrol_get_plugin('manual');
        // $instances = enrol_get_instances($modulecategory->id, true);
        // foreach ($instances as $instance) {
        //     if ($instance->enrol === 'manual') {
        //         break;
        //     }
        // }
        // if ($instance->enrol !== 'manual') {
        //     throw new \Exception('No manual enrol plugin in course');
        // }
        // $enrol->enrol_user($instance, $document->user_id, 0, $now, 0, ENROL_USER_ACTIVE, 0);

        // // Limit access
        // $role = get_config('moodle', 'defaultcourseroleid');
        // assign_capability('moodle/category:viewcourselist', CAP_ALLOW, $role, $modulecontext);

        foreach ($activities as $activity) {
            $filepath = sprintf('%s/%s.h5p', $h5pdir, $activity->activity_name);

            // Download activity
            $success = file_put_contents(
                $filepath,
                file_get_contents($activity->url)
            );

            if (!$success) {
                $errors[] = sprintf('%s (%s)', $activity->activity_name, get_string('erractivitydownload', 'local_nolej'));
                continue;
            }

            try {

                $record = (object) [
                    'name' => get_string('activities' . $activity->activity_name, 'local_nolej') . '.h5p',
                    'configdata' => '',
                    'contenttype' => 'contenttype_h5p',
                    'title' => get_string('activities' . $activity->activity_name, 'local_nolej') . '.h5p',
                    'author' => $document->user_id,
                    'type' => $activity->activity_name,
                    'contextid' => $modulecontext->id,
                    'filepath'  => '/'
                ];
                $contenttype = new \contenttype_h5p\contenttype($modulecontext);
                $h5pcontent = $contenttype->create_content($record);

                $filerecord = (object) [
                    'contextid' => $modulecontext->id,
                    'component' => \core_h5p\file_storage::COMPONENT,
                    'filearea'  => \core_h5p\file_storage::CONTENT_FILEAREA,
                    'itemid'    => $h5pcontent->get_id(),
                    'filepath'  => '/',
                    'filename'  => get_string('activities' . $activity->activity_name, 'local_nolej') . '.h5p',
                ];

                $file = $fs->create_file_from_pathname($filerecord, $filepath);

                $h5pfactory->get_framework()->set_file($file);
                $h5pcontent->import_file($file);

                $DB->insert_record(
                    'nolej_h5p',
                    (object) [
                        'document_id' => $document->document_id,
                        'tstamp' => $now,
                        'type' => $activity->activity_name,
                        'content_id' => $h5pcontent->get_id()
                    ],
                    false
                );

                /**
                 * Normal h5p object, not for content bank
                 */
                // $config = (object) [];
                // $h5pid = \core_h5p\helper::save_h5p($h5pfactory, $file, $config);
                // if ($h5pid == null) {
                //     $errors[] = sprintf('%s (%s)', $activity->activity_name, get_string('errh5psave', 'local_nolej'));
                //     continue;
                // }
                // if ($h5pid === false) {
                //     $errors[] = sprintf('%s (%s)', $activity->activity_name, get_string('errh5pvalidation', 'local_nolej'));
                //     continue;
                // }
                // $errors[] = sprintf('%s (%s %d)', $activity->activity_name, 'File saved with h5p id', $h5pid);

                // if ($file && $h5pcontent) {
                //     $updatedfilerecord = (object) [
                //         'id' => $file->get_id(),
                //         'itemid' => $h5pcontent->get_id()
                //     ];
                //     // As itemid changed, the pathnamehash has to be updated in the file table.
                //     $pathnamehash = \file_storage::get_pathname_hash(
                //         $file->get_contextid(),
                //         $file->get_component(),
                //         $file->get_filearea(),
                //         $updatedfilerecord->itemid,
                //         $file->get_filepath(),
                //         $file->get_filename()
                //     );
                //     $updatedfilerecord->pathnamehash = $pathnamehash;
                //     $DB->update_record('files', $updatedfilerecord);
                //     // The pathnamehash in the h5p table must match the file pathnamehash.
                //     $h5pfactory->get_framework()->updateContentFields($h5pid, ['pathnamehash' => $pathnamehash]);
                // }
            } catch (\Exception $e) {
                $errors[] = sprintf('%s (%s)', $activity->activity_name, 'Exception: ' . print_r($e));
            }
        }

        return $errors;
    }

    /**
     * Send notification to user
     *
     * @param string $documentid
     * @param int $userid
     * @param string $action used as language key for title
     * @param string $status
     * @param int $code
     * @param string $errormessage
     * @param int $credits
     * @param string $body language variable to use in mail body
     * @param ?object $vars parameters to use in $bodyVar's sprintf
     */
    public function sendnotification(
        $documentid,
        $userid,
        $action,
        $status,
        $code,
        $errormessage,
        $credits,
        $body,
        $vars = null
    ) {
        global $DB;

        $DB->insert_record(
            'nolej_activity',
            (object) [
                'document_id' => $documentid,
                'user_id' => $userid,
                'action' => $action,
                'tstamp' => time(),
                'status' => $status,
                'code' => $code,
                'error_message' => $errormessage,
                'consumed_credit' => $credits,
                'notified' => false
            ],
            false
        );

        $message = new \core\message\message();
        $message->component = 'local_nolej'; // Your plugin's name
        $message->name = $action; // Your notification name from message.php
        $message->userfrom = \core_user::get_noreply_user(); // If the message is 'from' a specific user you can set them here
        $message->userto = $userid;
        $message->subject = get_string('action_' . $action, 'local_nolej');
        $message->fullmessage = get_string('action_' . $action . '_body', 'local_nolej', $vars);
        $message->fullmessageformat = FORMAT_HTML;
        $message->fullmessagehtml = get_string($body, 'local_nolej', $vars);
        $message->smallmessage = get_string('action_' . $action, 'local_nolej');
        $message->notification = 1; // Because this is a notification generated from Moodle, not a user-to-user message
        $message->contexturl = substr($action, -2) == 'ok' // A relevant URL for the notification
            ? (new \moodle_url('/local/nolej/edit.php', ['documentid' => $documentid]))->out(false)
            : (new \moodle_url('/local/nolej/manage.php'))->out(false);
        $message->contexturlname = get_string('moduleview', 'local_nolej'); // Link title explaining where users get to for the contexturl
        // Extra content for specific processor
        // $content = [
        // '*' => [
        //     'header' => ' test ',
        //     'footer' => ' test ',
        // ],
        // ];
        // $message->set_additional_content('email', $content);
        $messageid = message_send($message);
        $this->log('Message sent with ID: ' . $messageid . ' to user ' . $userid);
    }

    protected function setLanguageOfUser(int $userid)
    {
        global $DB, $CFG, $USER;
        $user = $DB->get_record('user', array('id' => $userid));
        $preferred_language = $user->lang;

        if (isloggedin()) {
            $USER->lang = $preferred_language;
        } else {
            $CFG->lang = $preferred_language;
        }
    }
}