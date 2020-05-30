<?php
    require  __DIR__ .'/vendor/autoload.php';

    // Config
    $emailConfig = new stdClass();
    $emailConfig->subject = "Message from contact form";
    $emailConfig->to = "me@example.com";
    $emailConfig->origin = "http://example.com"; // Allowed origin
    $emailConfig->server = "mail.example.com";
    $emailConfig->from = '';
    $emailConfig->name = '';
    $emailConfig->message = '';
    $emailConfig->debug = false; // Enable verbose debug output

    if ($_SERVER['REQUEST_METHOD']==='OPTIONS') {
        // Respond to pre-flight requests
        header('Access-Control-Allow-Origin: '.$emailConfig->$origin);
        header('Access-Control-Allow-Methods: POST, OPTIONS');
        http_response_code(204);
    } else {
        if (isValidRequest($emailConfig)) {
            if (sendEmail($emailConfig)) {
                sendSuccessResponse($emailConfig);
            } else {
                sendFailureResponse($emailConfig);
            }
        } else {
            sendBadRequestResponse();
        }
    }

    function isValidRequest ($emailConfig) {
        $valid = true;
        $json = file_get_contents('php://input');
        $request_object = json_decode($json);

        $emailConfig->from = filter_var($request_object->email, FILTER_SANITIZE_EMAIL);
        $emailConfig->name = $request_object->name;

        // An empty message is fine with me, but the mailer complains
        if (empty($request_object->message)) {
            $emailConfig->message = 'Message was empty';
        } else {
            $emailConfig->message = $request_object->message;
        }

        // Method must be POST
        if ($_SERVER['REQUEST_METHOD']!=='POST') {
            $valid = false;
        }

        // From Email must not be empty
        if (empty($emailConfig->from)) {
            $valid = false;
        }

        // Name and From fields must not contain newlines bro
        if (preg_match( "/[\r\n]/", $emailConfig->name ) || preg_match( "/[\r\n]/", $emailConfig->from ) ) {
            $valid = false;
        }

        return $valid;
    }

    function sendBadRequestResponse () {
        http_response_code(400);
    }

    function sendSuccessResponse ($emailConfig) {
        $request = array('from' => $emailConfig->from, 'name' => $emailConfig->name, 'message' => $emailConfig->message);
        $result = array('result' => 'success', 'request' => $request);

        header('Content-Type: application/json');
        echo json_encode($result);
    }

    function sendFailureResponse ($emailConfig) {
        http_response_code(500);
        $result = array('result' => 'error', 'message' => $emailConfig->errorMessage);
        echo json_encode($result);
    }

    function sendEmail ($emailConfig) {
        $mailer = new PHPMailer(true);
        if ($emailConfig->debug) {
            $mailer->SMTPDebug = 3;
        }
        $mailer->setFrom($emailConfig->from, $emailConfig->name);
        $mailer->addAddress($emailConfig->to);

        $mailer->isHTML(true);
        $mailer->Subject = $emailConfig->subject;
        $mailer->Body    = $emailConfig->message;
        $mailer->AltBody = $emailConfig->message;

        if($mailer->send()) {
            return true;
        } else {
            $emailConfig->errorMessage = 'Message could not be sent. Mailer Error: ' . $mailer->ErrorInfo;
            return false;
        }
    }
?>
