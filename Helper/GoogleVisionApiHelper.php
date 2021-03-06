<?php

namespace Headoo\GoogleVisionApiBundle\Helper;


use Headoo\GoogleVisionApiBundle\Handler\GoogleVisionApiHandler;

class GoogleVisionApiHelper
{
    /**
     * @var string
     */
    private $_api_key;

    /**
     * There are only one URL For Now
     * @var string
     */
    private $_url = 'https://vision.googleapis.com/v1/images:annotate?key=';

    // Different types of detection
    const TYPE_UNSPECIFIED       = 'TYPE_UNSPECIFIED';
    const FACE_DETECTION         = 'FACE_DETECTION';
    const LANDMARK_DETECTION     = 'LANDMARK_DETECTION';
    const LOGO_DETECTION         = 'LOGO_DETECTION';
    const LABEL_DETECTION        = 'LABEL_DETECTION';
    const TEXT_DETECTION         = 'TEXT_DETECTION';
    const SAFE_SEARCH_DETECTION  = 'SAFE_SEARCH_DETECTION';
    const IMAGE_PROPERTIES       = 'IMAGE_PROPERTIES';
    const WEB_DETECTION          = 'WEB_DETECTION';


    /**
     * GoogleVisionApiHelper constructor.
     * @param $api_key
     */
    public function __construct($api_key){
        $this->_api_key    = $api_key;
    }


    /**
     * @param $base64Image
     * @param $type
     * @return array
     */
    private function _request($base64Image, $types){

        if (empty($types)) {
            throw new \InvalidArgumentException('Type missing from request.');
        }

        $url    = $this->_url . $this->_api_key;
        $json   ='{
			  	"requests": [
					{
					  "image": {
					    "content":"' .$base64Image. '"
					  },
					  "features": [';

        $lastType = end($types);

        foreach ($types as $type) {

            $json .= '
                            {
                                "type": "' .$type. '",
                                "maxResults": 200
                            }';
            if ($type !== $lastType) {
                $json .= ',';
            }
        }

        $json .= '    ]
					}
				]
			}';

        $data                       = $this->_makeCall($url,$json);
        $jsonResponse               = json_decode($data['raw_response']);

        if($data['http_code'] !== 200){
            $data['status']          = $jsonResponse->error->status;
            $data['message']         = $jsonResponse->error->message;
            $data['error']           = $jsonResponse->error;
            $data['parsed_response'] = [];
        }else{

            foreach ($types as $type) {
                $_type                  = strtolower($type);
                $_type                  = str_replace('_', ' ', $_type);
                $_type                  = ucwords($_type);
                $_type                  = str_replace(' ', '', $_type);
                $parseFunction          = '_parse' . $_type;
                $data['parsed_response'][$type] = $this->$parseFunction($jsonResponse->responses[0]);
            }

            $data['raw_response'] = $jsonResponse->responses;
        }

        return $data;
    }

    /**
     * @param $url
     * @param $json
     * @return array
     */
    private function _makeCall($url, $json){
        $data = [];

        $curl                       = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER,
            array("Content-type: application/json"));
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $json);
        $jsonResponse               = curl_exec($curl);
        $httpCode                   = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        $data['http_code']          = $httpCode;
        $data['raw_response']       = $jsonResponse;

        return $data;
    }

    /**
     * @param string $image
     * @param array $type
     * @return array
     */
    public function vision($image, $types = []){

        if (preg_match("#^https?://.+#", $image) || substr($image,0,1) == '/') {
            $data               = file_get_contents($image);
            $base64Image        = base64_encode($data);
        }
        else{
            $mediaBase64        = explode(";",  $image);
            $base64Image        = explode(",",  $mediaBase64[1]);
        }

        return $this->_request($base64Image, $types);
    }

    /**
     * @param $response
     * @return mixed
     */
    private function _parseTypeUnspecified($response){
        return $response;
    }

    /**
     * @param $response
     * @return array
     */
    private function _parseFaceDetection($response){
        $_faceAnnotations       = [];
        if(isset($response->faceAnnotations)){
            foreach($response->faceAnnotations as $faceAnnotation){
                $_faceAnnotations[]     = GoogleVisionApiHandler::objectifyFaceAnnotation($faceAnnotation);
            }
        }
        return $_faceAnnotations;
    }


    /**
     * @param $response
     * @return array
     */
    private function _parseLandmarkDetection($response){
        $_landmarkAnnotations   = [];
        if(isset($response->landmarkAnnotations)){
            foreach($response->landmarkAnnotations as $landmarkAnnotation){
                $_landmarkAnnotations[]     = GoogleVisionApiHandler::objectifyLandmarkAnnotation($landmarkAnnotation);
            }
        }
        return $_landmarkAnnotations;
    }

    /**
     * @param $response
     * @return array
     */
    private function _parseLogoDetection($response){
        $_logoAnnotations       = [];

        if(isset($response->logoAnnotations)){
            foreach($response->logoAnnotations as $logoAnnotation){
                $_logoAnnotations[]     = GoogleVisionApiHandler::objectifyLogoAnnotation($logoAnnotation);
            }
        }
        return $_logoAnnotations;
    }

    /**
     * @param $response
     * @return array
     */
    private function _parseLabelDetection($response){
        $_labelAnnotations      = [];

        if(isset($response->labelAnnotations)){
            foreach($response->labelAnnotations as $labelAnnotation){
                $_labelAnnotations[]     = GoogleVisionApiHandler::objectifyLabelAnnotation($labelAnnotation);
            }
        }
        return $_labelAnnotations;
    }

    /**
     * @param $response
     * @return array
     */
    private function _parseTextDetection($response){
        $_textAnnotations      = [];

        if(isset($response->textAnnotations)){
            foreach($response->textAnnotations as $textAnnotation){
                $_textAnnotations[]     = GoogleVisionApiHandler::objectifyTextAnnotation($textAnnotation);
            }
        }
        return $_textAnnotations;
    }

    /**
     * @param $response
     * @return \Headoo\GoogleVisionApiBundle\Annotations\SafeSearchAnnotation
     */
    private function _parseSafeSearchDetection($response){
        return GoogleVisionApiHandler::objectifySafeSearchAnnotation($response->safeSearchAnnotation);
    }

    /**
     * @param $response
     * @return \Headoo\GoogleVisionApiBundle\Properties\ImageProperties
     */
    private function _parseImageProperties($response){
        return GoogleVisionApiHandler::objectifyImagePropertiesAnnotation($response->imagePropertiesAnnotation);
    }
}
