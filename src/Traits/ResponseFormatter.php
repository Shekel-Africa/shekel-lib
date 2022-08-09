<?php

namespace Shekel\ShekelLib\Traits;

trait ResponseFormatter {
    
    /**
     * Return JSON Response
     * 
     * @param array $data
     * @param int $code
     * @return JsonResponse
     */
    public function jsonResponse(array $data =[], string $message, int $code = 200) 
    {
        if ($message) {
            $data['message'] = $message;
        }
        return response()->json($data, $code);
    }

    /**
     * Some operation (save only?) has completed successfully
     * @param mixed $data
     * @return mixed
     */
    public function respondWithSuccess($data, $message = '', $code = 200)
    {
        return $this->jsonResponse(
            is_array($data) ? $data : ['data' => $data],
            $message,
            $code
        );
    }

    /**
     * Respond with an Error
     * @param string $data
     * @param int $code
     * @return JsonResponse
     */
    public function respondWithError($data=[], $message='There was an error', $code = 400)
    {
        return $this->jsonResponse(
            is_array($data) ? $data : ['errors' => $data], 
            $message,
            $code
        );
    }
}