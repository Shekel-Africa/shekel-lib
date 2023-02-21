<?php

namespace Shekel\ShekelLib\Traits;

use GuzzleHttp\Psr7\Response;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;

trait ResponseFormatter {

    /**
     * Return JSON Response
     *
     * @param array $data
     * @param int $code
     * @return JsonResponse
     */
    public function jsonResponse(array $data =[], string $message ='', int $code = 200): JsonResponse
    {
        if ($message) {
            $data['message'] = $message;
        }
        return response()->json($data, $code);
    }

    /**
     * Some operation (save only?) has completed successfully
     * @param mixed $data
     * @param string $message
     * @param int $code
     * @return JsonResponse
     */
    public function respondWithSuccess($data, string $message = '', int $code = 200): JsonResponse
    {
        return $this->jsonResponse(
            is_array($data) ? $data : ['data' => $data],
            $message,
            $code
        );
    }

    /**
     * Respond with an Error
     * @param mixed $data
     * @param string $message
     * @param int $code
     * @param $e
     * @return JsonResponse
     */
    public function respondWithError($data=[], string $message='There was an error', int $code = 400, $e=null): JsonResponse
    {
        if (isset($e) && $e instanceof ModelNotFoundException) {
            return $this->jsonResponse([], 'Data not found.', 404);
        }
        return $this->jsonResponse(
            is_array($data) ? $data : ['errors' => $data],
            $message,
            $code
        );
    }
}
