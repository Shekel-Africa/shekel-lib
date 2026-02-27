<?php

namespace Shekel\ShekelLib\Services\v3;


use Illuminate\Http\Client\ConnectionException;

class MessagingService extends ShekelBaseService {

    public function __construct(){
        parent::__construct("messaging");
    }

    public function alert($data)
    {
        try {
            $url = "/send/alert";
            return $this->handleRequest($this->client->post($url, $data));
        } catch (\Throwable $th) {}
    }

    public function closeThread($data)
    {
        $url = "/chats/close-ch-thread";
        return $this->handleRequest($this->client->post($url, $data));
    }

    public function newChatMessage($data)
    {
        $url = "/chats/threads";
        return $this->handleRequest($this->client->post($url, $data));
    }

    public function sendEmailVerification($data)
    {
        $url = "/send/verification-email";
        return $this->handleRequest($this->client->post($url, $data));
    }

    public function sendInviteEmail($data)
    {
        $url = "/send/invite-email";
        return $this->handleRequest($this->client->post($url, $data));
    }

    public function sendOTP($data) {
        $url = "/send/otp-email";
        return $this->handleRequest($this->client->post($url, $data));
    }

    public function sendOtpPhone($phone, $otp) {
        $url = "/send/otp/phone";
        return $this->handleRequest($this->client->post($url, [
            'phone' => $phone,
            'otp_code' => $otp
        ]));
    }

    /**
     * @param array{
     *     loan_id: string,
     *     email_address: string,
     *     requester_id: string,
     *     name: string,
     *     car_name: string,
     *     amount: numeric-string,
     *     currency: string,
     *     tag: string
     * } $data
     */
    public function sendLoanOfferEmail(array $data) {
        $url = "/send/loan-offer-email";
        return $this->handleRequest($this->client->post($url, $data));
    }

    /**
     * @param array{
     *     loan_id: string,
     *     email_address: string,
     *     user_id: string,
     *     name: string,
     *     car_name: string,
     *     amount: numeric-string,
     *     currency: string
     * } $data
     */
    public function sendLoanRequestEmail(array $data) {
        $url = "/send/loan-request-email";
        return $this->handleRequest($this->client->post($url, $data));
    }

    /**
     * @param array{
     *     email: string,
     *     store: string,
     *     carName: string,
     *     customer_email: string,
     *     phone: string,
     *     date: string,
     *     time: string
     * } $data
     */
    public function sendBookingInspectionEmail(array $data) {
        $url = "/send/book-inspection";
        return $this->handleRequest($this->client->post($url, $data));
    }
    /**
     * @param array{
     *     email: string,
     *     store: string,
     *     name: string,
     *     customer_email: string,
     *     phone: string,
     *     reason: string,
     *     description: string
     * } $data
     */
    public function sendContactDealerEmail(array $data) {
        $url = "/send/contact-dealer";
        return $this->handleRequest($this->client->post($url, $data));
    }

    /**
     * @param array{
     *     email: string,
     *     name: string
     * } $data
     */
    public function sendReportEmail(array $data, $file) {
        $url = "/send/report";
        return $this->handleRequest(
            $this->client->attach('file', $file)->post($url, $data)
        );
    }
    public function sendLoanRejectionEmail($data) {
        $url = "/send/loan-rejection-email";
        return $this->handleRequest($this->client->post($url, $data));
    }

    public function sendAdminVerificationMail($data) {
        $url = "/send/kyc-verified-email";
        return $this->handleRequest($this->client->post($url, $data));
    }

    public function sendForgotPasswordMail($data) {
        $url = "/send/forgot-password";
        return $this->handleRequest($this->client->post($url, $data));
    }

    public function onNewLoanComment($data) {
        $url = "/send/on-new-loan-comment";
        return $this->handleRequest($this->client->post($url, $data));
    }

    public function moneyInEscrow($data) {
        $url = "/send/money-in-escrow";
        return $this->handleRequest($this->client->post($url, $data));
    }
    public function buyerConsent($data) {
        $url = "/send/buyer-consent";
        return $this->handleRequest($this->client->post($url, $data));
    }
    public function tradeComplete($data) {
        $url = "/send/trade-complete";
        return $this->handleRequest($this->client->post($url, $data));
    }

    public function sendDisbursementEmail($data) {
        $url = "/send/disbursement-email";
        return $this->handleRequest($this->client->post($url, $data));
    }

    public function sendKYCNotification($data) {
        $url = "/send/kyc-status-email";
        return $this->handleRequest($this->client->post($url, $data));
    }
    
    public function sendTransactionNotification($data) {
        $url = "/send/transaction-notification";
        return $this->handleRequest($this->client->post($url, $data));
    }

    public function sendDueLoanNotification($data) {
        $url = "/send/due-loan-notification";
        return $this->handleRequest($this->client->post($url, $data));
    }

    public function sendBirthdayNotifications($data) {
        $url = "/send/birthday-notifications";
        return $this->handleRequest($this->client->post($url, $data));
    }

    public function sendComment($object_id, $object_type, $data) {
        $url = "/admin/comment";
        $data['object_id'] = $object_id;
        $data['object_type'] = $object_type;
        return $this->handleRequest($this->client->post($url, $data));
    }

    /**
     * @param array{name:string,url:string,email:string,sender:string}$data
     */
    public function sendInvoiceLink(array $data) {
        $url = "/send/invoice-link";
        return $this->handleRequest($this->client->post($url, $data));
    }
    
    /**
     * @param array{request_id: string} $data
     */
    public function sendKycSubmissionReminder(string $toEmail, string $toName, array $data)
    {
        $url = "/send/kyc-submission-reminder";
        $parameters = [
            'email' => $toEmail,
            'name' => $toName,
            'data' => $data
        ];
        return $this->handleRequest($this->client->post($url, $parameters));
    }

    /**
     * @param array{loan_id: string} $data
     */
    public function sendRestructureSuggestionNotification(string $toEmail, string $toName, array $data) {
        $url = '/send/restructure-suggestion';
        $parameters = [
            'email' => $toEmail,
            'name' => $toName,
            'data' => $data
        ];
        return $this->handleRequest($this->client->post($url, $parameters));
    }
    
    /**
     * @param array{loan_id: string, brand: string, model: string} $data
     */
    public function sendSellSuggestionNotification(string $toEmail, string $toName, array $data) {
        $url = '/send/sell-suggestion';
        $parameters = [
            'email' => $toEmail,
            'name' => $toName,
            'data' => $data
        ];
        return $this->handleRequest($this->client->post($url, $parameters));
    }

    public function sendFoundersWelcome(string $toEmail, string $toName) {
        $url = '/send/founders-welcome';
        $parameters = [
            'email' => $toEmail,
            'name' => $toName
        ];
        return $this->handleRequest($this->client->post($url, $parameters));
    }

    /**
     * @param array{loan_id: string, amount: string, brand: string, model: string, currency?: string} $data
     */
    public function sendLoanRequestConfirmation(string $toEmail, string $toName, array $data) {
        $url = '/send/loan-request-confirmation';
        $parameters = [
            'email' => $toEmail,
            'name' => $toName,
            'data' => $data
        ];
        return $this->handleRequest($this->client->post($url, $parameters));
    }

    public function getFirstComment($object_id, $object_type) {
        $url = "/comment/$object_type/$object_id/first";
        return $this->handleRequest($this->client->get($url));
    }

    /**
     * @param array{
     *     email: string,
     *     name: string,
     *     car_name: string,
     *     vin: string,
     *     repayment_amount: numeric,
     *     estimated_arrival: string,
     *     user_id: string,
     *     currency: string
     * } $data
     */
    public function sendTwoWeeksShippingNotification($data) {
        $url = "/send/shipping/two-week";
        return $this->handleRequest($this->client->post($url, $data));
    }

    /**
     * @param array{
     *     email: string,
     *     name: string,
     *     car_name: string,
     *     vin: string,
     *     repayment_amount: numeric,
     *     estimated_arrival: string,
     *     user_id: string,
     *     currency: string
     * } $data
     */
    public function sendOneWeekShippingNotification($data) {
        $url = "/send/shipping/one-week";
        return $this->handleRequest($this->client->post($url, $data));
    }

    /**
     * @param array{
     *     email: string,
     *     name: string,
     *     car_name: string,
     *     vin: string,
     *     repayment_amount: numeric,
     *     estimated_arrival: string,
     *     user_id: string,
     *     currency: string
     * } $data
     */
    public function sendOneDayShippingNotification($data) {
        $url = "/send/shipping/one-day";
        return $this->handleRequest($this->client->post($url, $data));
    }

    /**
     * @param array{
     *     email: string,
     *     name: string,
     *     car_name: string,
     *     vin: string,
     *     repayment_amount: numeric,
     *     estimated_arrival: string,
     *     user_id: string,
     *     currency: string
     * } $data
     */
    public function sendShippingEstimateNotification($data) {
        $url = "/send/shipping/estimate";
        return $this->handleRequest($this->client->post($url, $data));
    }

    /**
     * @param array{
     *     email: string,
     *     name: string,
     *     car: string,
     *     vin: string,
     *     utilized_amount: numeric,
     *     business: string,
     *     partner_id: string,
     *     contract_id: string,
     *     currency: string,
     *     import_country?: string
     * } $data
     */
    public function sendPartnerUtilizedNotification(array $data) {
        $url = "/send/partner/deal";
        return $this->handleRequest($this->client->post($url, $data));
    }
    /**
     * @param array{
     *     email: string,
     *     name: string,
     *     car: string,
     *     vin: string,
     *     utilized_amount: numeric,
     *     repayment_amount: numeric,
     *     business: string,
     *     partner_id: string,
     *     contract_id: string,
     *     currency: string,
     *     import_country?: string
     * } $data
     */
    public function sendPartnerRepaidNotification(array $data) {
        $url = "/send/partner/repaid";
        return $this->handleRequest($this->client->post($url, $data));
    }

    /**
     * @param array{
     *     email: string,
     *     name: string,
     *     car: string,
     *     vin: string,
     *     utilized_amount: numeric,
     *     business: string,
     *     partner_id: string,
     *     currency: string,
     *     import_country?: string
     * } $data
     */
    public function sendPartnerPreApprovalNotification(array $data) {
        $url = "/send/partner/approval";
        return $this->handleRequest($this->client->post($url, $data));
    }


}
