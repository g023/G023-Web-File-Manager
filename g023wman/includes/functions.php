<?php

function jsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function errorResponse($message, $status = 400) {
    jsonResponse(['error' => $message], $status);
}

function getInput() {
    $json = json_decode(file_get_contents('php://input'), true);
    if (is_array($json)) {
        return $json;
    }
    return $_POST;
}
