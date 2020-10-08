<?php


function createRequest($f3, $type, $url, $params){
    $apiTrackingMapper=new DB\SQL\Mapper($f3->get('db'),'apitracking');

    $apiTrackingMapper->type = $type;
    $apiTrackingMapper->url = $url;
    $apiTrackingMapper->params = '';
    $apiTrackingMapper->created_dt = date("Y-m-d H:i:s");
    $apiTrackingMapper->save();

    return $apiTrackingMapper;
}

function updateRequest($request, $response){

    $reqDate = new Datetime($request->created_dt);
    $now = new Datetime();

    $diff = $now->diff($reqDate);
    $request->response = $response;
    $request->execution_time = $diff->s;
    $request->save();
}

