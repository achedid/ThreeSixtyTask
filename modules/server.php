<?php

class ServerModule{
    function validateSubscription($f3){

        $possible_responses = array('INVALID_MSISDN', 'SUCCESS', 'BLACKLISTED');
        $response = $possible_responses[array_rand($possible_responses, 1)];
        $status = $response == 'SUCCESS' ? 'SUCCESS' : 'FAIL';
        $message = $response == 'SUCCESS' ? '' : $response;

        header('Content-Type: application/json');
        echo json_encode(array('status' => $status, 'message'=> $message));
    }

    function unSubscribe(){

        header('Content-Type: application/json');
        echo json_encode(array('status' => 'SUCCESS'));
    }
}
