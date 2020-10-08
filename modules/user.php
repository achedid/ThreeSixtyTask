<?php
require 'apitracking.php';

use \Firebase\JWT\JWT;

class UserModule
{

    function getServerUrl($path)
    {
        return 'http://'.$_SERVER['HTTP_HOST'].'/360vuz/server/' . $path;
    }

    function generateJWTTestingData($f3)
    {

        $exp = new Datetime();
        $exp->modify("+60 minutes");
        $privateKey = file_get_contents("keys/id_rsa");
        $nbf = new DateTime();
        $payload = array(
            'iss' => '360vuz',
            'exp' => $exp->getTimestamp(),
            'sub' => 'subscribe',
            'aud' => 'appUser',
            'nbf' => $nbf->getTimestamp(),
            'iat' => $nbf->getTimestamp(),
            'jti' => '360VUZJWT',
            'userId' => '1',
            'subscriptionId' => '1',
            'msisdn' => $f3->get('PARAMS.msisdn'),
            'operatorId' => '1'
        );

        $jwt = JWT::encode($payload, $privateKey, 'RS256');

        header('Content-Type: application/json');

        echo json_encode(array('jwt' => $jwt, 'payload' => $payload));
    }

    function subscribe($f3)
    {

        $req = createRequest($f3, "user", $f3->get('PARAMS.0'), '');

        $jwtToken = $f3->get('PARAMS.subscriptionToken');
        $authorization = $f3->get('HEADERS.Authorization');

        if ($authorization == "Bearer " . $jwtToken) {

            $pubKey = file_get_contents("keys/id_rsa.pub");

            $status = 'FAIL';
            $message = 'UNKNOWN_ERROR';

            try {
                $jsonBody = json_decode($f3->get('BODY'));
                $encoded = JWT::decode($jwtToken, $pubKey, array('RS256'));
                $payload = (array)$encoded;

                if (count(array_diff($payload, (array)$jsonBody)) == 0) {

                    $subscriptionId = $payload['subscriptionId'];
                    $msisdn = $payload['msisdn'];
                    $userId = $payload['userId'];
                    $operatorId = $payload['operatorId'];

                    $subscriber = $this->getOrCreateSubscriber($f3, $msisdn, $operatorId, true);

                    if ($subscriber) {

                        $user_subscription = $this->getOrCreateSubscription($f3, $subscriber, $subscriptionId, true);

                        if ($user_subscription) {

                            if ($user_subscription->status == 'PENDING' || $user_subscription->status == 'INACTIVE') {

                                $result = $this->ServerValidationSubscription($f3, $subscriber->msisdn, $subscriptionId);
                                $valid = $result->status == 'SUCCESS';

                                $user_subscription->status = $valid ? 'ACTIVE' : 'INACTIVE';
                                $user_subscription->updated_dt = date("Y-m-d H:i:s");
                                $user_subscription->save();

                                if ($valid) {
                                    $status = 'SUCCESS';
                                    $message = '';
                                } else {
                                    $message = $result->message;
                                }

                            } else if ($user_subscription->status == 'ACTIVE') {
                                $message = 'SUBSCRIPTION_ALREADY_ACTIVE';
                            }
                        } else {
                            $message = 'CANNOT_CREATE_SUBSCRIPTION';
                        }
                    } else {
                        $message = 'CANNOT_CREATE_SUBSCRIBER';
                    }
                } else {
                    $message = 'PAYLOAD_MISMATCH';
                }

            } catch (Exception $e) {
                $message = 'INVALID_SIGNATURE';
            }

        } else {
            $message = 'INVALID_AUTHORIZATION';
        }

        header('Content-Type: application/json');
        $response = json_encode(array('status' => $status, 'message' => $message));

        updateRequest($req, var_export($response, true));

        echo $response;
    }

    function unsubscribe($f3)
    {
        $req = createRequest($f3, "user", $f3->get('PARAMS.0'), '');

        $jwtToken = $f3->get('PARAMS.unsubscriptionToken');
        $authorization = $f3->get('HEADERS.Authorization');

        if ($authorization == "Bearer " . $jwtToken) {

            $pubKey = file_get_contents("keys/id_rsa.pub");

            $status = 'FAIL';
            $message = 'UNKNOWN_ERROR';

            try {
                $jsonBody = json_decode($f3->get('BODY'));
                $encoded = JWT::decode($jwtToken, $pubKey, array('RS256'));
                $payload = (array)$encoded;

                if (count(array_diff($payload, (array)$jsonBody)) == 0) {

                    $subscriptionId = $payload['subscriptionId'];
                    $msisdn = $payload['msisdn'];
                    $operatorId = $payload['operatorId'];

                    $subscriber = $this->getOrCreateSubscriber($f3, $msisdn, $operatorId);

                    if ($subscriber) {

                        $user_subscription = $this->getOrCreateSubscription($f3, $subscriber, $subscriptionId);

                        if ($user_subscription) {

                            if ($user_subscription->status == 'ACTIVE') {

                                $result = $this->ServerValidationUnSubscription($f3, $subscriber->msisdn);
                                $valid = $result->status == 'SUCCESS';

                                $user_subscription->status = 'INACTIVE';
                                $user_subscription->updated_dt = date("Y-m-d H:i:s");
                                $user_subscription->save();

                                if ($valid) {
                                    $status = 'SUCCESS';
                                    $message = '';
                                }

                            } else {
                                $message = 'SUBSCRIPTION_ALREADY_NOT_ACTIVE';
                            }
                        } else {
                            $message = 'NO_SUBSCRIPTION_FOUND';
                        }
                    } else {
                        $message = 'SUBSCRIBER_NOT_FOUND';
                    }
                } else {
                    $message = 'PAYLOAD_MISMATCH';
                }

            } catch (Exception $e) {
                $message = 'INVALID_SIGNATURE';
            }

        } else {
            $message = 'INVALID_AUTHORIZATION';
        }

        header('Content-Type: application/json');
        $response = json_encode(array('status' => $status, 'message' => $message));

        updateRequest($req, var_export($response, true));

        echo $response;
    }

    function getOrCreateSubscription($f3, $subscriber, $subscription_id, $autoCreate=false)
    {
        $subscriptionMapper = new DB\SQL\Mapper($f3->get('db'), 'UserSubscription');
        $userSubscription = $subscriptionMapper->load(array('subscription_id=? AND subscriber_id=?', $subscription_id, $subscriber->id));
        if (!$userSubscription && $autoCreate) {
            $subscriptionMapper->subscriber_id = $subscriber->id;
            $subscriptionMapper->subscription_id = $subscription_id;
            $subscriptionMapper->status = 'PENDING';
            $subscriptionMapper->created_dt = date("Y-m-d H:i:s");
            $subscriptionMapper->save();
            $userSubscription = $subscriptionMapper;
        }
        return $userSubscription;
    }

    function getOrCreateSubscriber($f3, $msisdn, $operator_id, $auto_create=false)
    {
        $subscriberMapper = new DB\SQL\Mapper($f3->get('db'), 'Subscriber');
        $subscriber = $subscriberMapper->load(array('msisdn=? AND operator_id=?', $msisdn, $operator_id));

        if (!$subscriber && $auto_create) {
            $subscriberMapper->public_id = uniqid();
            $subscriberMapper->msisdn = $msisdn;
            $subscriberMapper->operator_id = $operator_id;
            $subscriberMapper->created_dt = date("Y-m-d H:i:s");
            $subscriberMapper->save();
            $subscriber = $subscriberMapper;
        }
        return $subscriber;
    }

    function ServerValidationSubscription($f3, $msisdn)
    {
        $url = $this->getServerUrl('validateSubscription');

        $req = createRequest($f3, "server", $url, $msisdn);

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

        $result = curl_exec($curl);
        curl_close($curl);

        updateRequest($req, var_export($result, true));

        return json_decode($result);
    }

    function ServerValidationUnSubscription($f3, $msisdn)
    {
        $url = $this->getServerUrl('validateUnSubscription');

        $req = createRequest($f3, "server", $url, $msisdn);

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

        $result = curl_exec($curl);
        curl_close($curl);
        updateRequest($req, var_export($result, true));

        return json_decode($result);
    }
}
