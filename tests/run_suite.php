<?php
/**
 * Test Suite for RexPayPHP SDK
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Pils36\Rexpay;
use Pils36\Rexpay\Helpers\Router;
use Pils36\Rexpay\Http\RequestBuilder;
use Pils36\Rexpay\Http\Request;
use Pils36\Rexpay\Http\Response;
use Pils36\Rexpay\Event;
use Pils36\Rexpay\Fee;
use Pils36\Rexpay\Contracts\RouteInterface;
use Pils36\Rexpay\Exception\ApiException;
use Pils36\Rexpay\Exception\ValidationException;

class TestRunner
{
    private int $passed = 0;
    private int $failed = 0;
    private array $errors = [];

    public function run()
    {
        echo "=========================================================\n";
        echo "REXPAY PHP SDK COMPREHENSIVE TEST SUITE (PHP " . PHP_VERSION . ")\n";
        echo "=========================================================\n\n";

        $this->testClientInstantiation();
        $this->testConstructorAuthAutoInjection();
        $this->testRouteResolution();
        $this->testTransactionInitializeBuilder();
        $this->testTransactionMakePaymentBuilder();
        $this->testTransactionVerifyBuilder();
        $this->testVarDumpCheck();
        $this->testBasicAuthHeaderGeneration();
        $this->testGuzzleHeaderOmissionBug();
        $this->testCurlLiveModeUrlSwitching();
        $this->testResponseParsingSuccess();
        $this->testResponseParsingNullErrorHandling();
        $this->testResponseHelpers();
        $this->testWebhookEventSignature();
        $this->testFeeCalculation();

        echo "\n---------------------------------------------------------\n";
        echo "TEST SUMMARY: {$this->passed} Passed, {$this->failed} Failed\n";
        echo "---------------------------------------------------------\n";

        if (!empty($this->errors)) {
            echo "\nDetailed Failures / Discovered Bugs:\n";
            foreach ($this->errors as $err) {
                echo "  [FAIL] $err\n";
            }
        }
    }

    private function assert($condition, string $testName, string $failureDetails = '')
    {
        if ($condition) {
            $this->passed++;
            echo "  [PASS] $testName\n";
        } else {
            $this->failed++;
            $msg = $testName . ($failureDetails ? ": $failureDetails" : '');
            $this->errors[] = $msg;
            echo "  [FAIL] $msg\n";
        }
    }

    private function testClientInstantiation()
    {
        echo "Test Suite 1: Client Instantiation & Configuration\n";
        $rexpay = new Rexpay();
        $this->assert($rexpay instanceof Rexpay, "Rexpay client instantiated");
        $this->assert($rexpay->use_guzzle === false, "Default transport is cURL (use_guzzle=false)");
        $rexpay->useGuzzle();
        $this->assert($rexpay->use_guzzle === true, "useGuzzle() successfully sets use_guzzle=true");
    }

    private function testConstructorAuthAutoInjection()
    {
        echo "\nTest Suite 2: Modern Constructor & Auto-Auth Injection\n";
        $user = 'merchant_user';
        $secret = 'secret_pass_123';
        $expectedToken = base64_encode("{$user}:{$secret}");

        $rexpay = new Rexpay($user, $secret, 'production');
        $this->assert($rexpay->auth_token === $expectedToken, "Constructor automatically generates base64 authToken");
        $this->assert($rexpay->mode === 'production', "Constructor configures environment mode");

        // Test RequestBuilder auto-injects credentials if omitted from payload
        $interface = \Pils36\Rexpay\Routes\Transaction::initialize();
        $builder = new RequestBuilder($rexpay, $interface, [
            'reference' => 'AUTO_REF',
            'amount' => 1000
        ]);
        $request = $builder->build();
        $body = json_decode($request->body);

        $this->assert($body->authToken === $expectedToken, "Auto-injected authToken into request payload");
        $this->assert($body->mode === 'production', "Auto-injected mode into request payload");
        $this->assert(isset($request->headers['Authorization']), "Authorization header automatically set");
    }

    private function testRouteResolution()
    {
        echo "\nTest Suite 3: Router & Dynamic Dispatch\n";
        $rexpay = new Rexpay();
        $router = $rexpay->transaction;
        $this->assert($router instanceof Router, "Magic __get returns Router instance for 'transaction'");

        try {
            $invalid = $rexpay->nonexistent_route;
            $this->assert(false, "Unknown route throws ValidationException", "Did not throw");
        } catch (ValidationException $e) {
            $this->assert(true, "Unknown route throws ValidationException");
        }
    }

    private function testTransactionInitializeBuilder()
    {
        echo "\nTest Suite 4: Transaction Initialize Endpoint & Payload\n";
        $rexpay = new Rexpay();
        
        ob_start();
        $interface = \Pils36\Rexpay\Routes\Transaction::initialize();
        $output = ob_get_clean();

        $this->assert(
            $interface[RouteInterface::ENDPOINT_KEY] === '/payment/v2/createPayment',
            "Transaction::initialize endpoint is '/payment/v2/createPayment'"
        );
        $this->assert(
            $interface[RouteInterface::METHOD_KEY] === RouteInterface::POST_METHOD,
            "Transaction::initialize method is POST"
        );

        $payload = [
            'reference' => 'TEST_REF_123',
            'amount' => 5000,
            'currency' => 'NGN',
            'userId' => 'customer@example.com',
            'callbackUrl' => 'https://example.com/callback',
            'authToken' => base64_encode('user:pass'),
            'mode' => 'test'
        ];

        $builder = new RequestBuilder($rexpay, $interface, $payload);
        $request = $builder->build();

        $this->assert(
            $request->endpoint === 'https://pgs-sandbox.globalaccelerex.com/api/pgs/payment/v2/createPayment',
            "RequestBuilder correctly prepends sandbox PGS root for initialize"
        );
        $this->assert(
            !empty($request->body) && json_decode($request->body)->reference === 'TEST_REF_123',
            "Payload correctly serialized as JSON in request body"
        );
    }

    private function testTransactionMakePaymentBuilder()
    {
        echo "\nTest Suite 5: Direct Payment Processing Endpoint (makePayment)\n";
        $rexpay = new Rexpay();
        $interface = \Pils36\Rexpay\Routes\Transaction::makePayment();

        $this->assert(
            $interface[RouteInterface::ENDPOINT_KEY] === '/payment/v1/makePayment',
            "Transaction::makePayment endpoint is '/payment/v1/makePayment'"
        );
        $this->assert(
            $interface[RouteInterface::METHOD_KEY] === RouteInterface::POST_METHOD,
            "Transaction::makePayment method is POST"
        );
    }

    private function testTransactionVerifyBuilder()
    {
        echo "\nTest Suite 6: Transaction Verify Endpoint & Routing\n";
        $rexpay = new Rexpay();
        $interface = \Pils36\Rexpay\Routes\Transaction::verify();

        $this->assert(
            $interface[RouteInterface::ENDPOINT_KEY] === '/getTransactionStatus',
            "Transaction::verify endpoint is '/getTransactionStatus'"
        );

        $payload = [
            'transactionReference' => 'TXN_998877',
            'authToken' => base64_encode('user:pass'),
            'mode' => 'test'
        ];

        $builder = new RequestBuilder($rexpay, $interface, $payload);
        $request = $builder->build();

        $this->assert(
            $request->endpoint === 'https://pgs-sandbox.globalaccelerex.com/api/cps/v1/getTransactionStatus',
            "Verify correctly routes to CPS v1 endpoint (/api/cps/v1/getTransactionStatus)"
        );
    }

    private function testVarDumpCheck()
    {
        echo "\nTest Suite 7: Code Quality & Stray Debug Dump Detection\n";
        ob_start();
        \Pils36\Rexpay\Routes\Transaction::initialize();
        $output = ob_get_clean();

        $this->assert(
            empty($output),
            "No stray debug output in Transaction::initialize()",
            "Found stray output in stdout: " . trim($output)
        );
    }

    private function testBasicAuthHeaderGeneration()
    {
        echo "\nTest Suite 8: Basic Auth Header Generation (cURL / file_get_contents)\n";
        $rexpay = new Rexpay();
        $token = base64_encode('user:pass');
        $interface = \Pils36\Rexpay\Routes\Transaction::verify();
        $builder = new RequestBuilder($rexpay, $interface, [
            'transactionReference' => 'REF_1',
            'authToken' => $token,
            'mode' => 'test'
        ]);
        $request = $builder->build();
        $headers = $request->flattenedHeaders();

        $foundAuth = false;
        foreach ($headers as $h) {
            if ($h === 'Authorization: Basic ' . $token) {
                $foundAuth = true;
                break;
            }
        }
        $this->assert($foundAuth, "flattenedHeaders() includes 'Authorization: Basic <token>'");
    }

    private function testGuzzleHeaderOmissionBug()
    {
        echo "\nTest Suite 9: Guzzle Transport Authorization Header Integrity\n";
        $rexpay = new Rexpay();
        $rexpay->useGuzzle();
        $token = base64_encode('user:pass');
        $interface = \Pils36\Rexpay\Routes\Transaction::verify();
        $builder = new RequestBuilder($rexpay, $interface, [
            'transactionReference' => 'REF_1',
            'authToken' => $token,
            'mode' => 'test'
        ]);
        $request = $builder->build();

        $this->assert(
            isset($request->headers['Authorization']),
            "Guzzle transport headers include Authorization header"
        );
    }

    private function testCurlLiveModeUrlSwitching()
    {
        echo "\nTest Suite 10: Environment URL Resolution (Test vs Live)\n";
        $rexpay = new Rexpay();
        $interface = \Pils36\Rexpay\Routes\Transaction::verify();
        $builder = new RequestBuilder($rexpay, $interface, [
            'transactionReference' => 'REF_LIVE',
            'authToken' => 'token',
            'mode' => 'production'
        ]);
        $request = $builder->build();

        $this->assert(
            $request->endpoint === 'https://cps.globalaccelerex.com/getTransactionStatus',
            "Live mode switches verification URL to https://cps.globalaccelerex.com/getTransactionStatus"
        );
    }

    private function testResponseParsingSuccess()
    {
        echo "\nTest Suite 11: Response Parsing on Valid API Response\n";
        $response = new Response();
        $response->okay = true;
        $response->forApi = true;
        $response->body = json_encode([
            'responseCode' => '00',
            'status' => 'success',
            'message' => 'Approved',
            'data' => ['amount' => 5000]
        ]);

        try {
            $parsed = $response->wrapUp();
            $this->assert($parsed->responseCode === '00', "Valid JSON response parses correctly");
        } catch (\Throwable $e) {
            $this->assert(false, "Valid JSON response parses correctly", $e->getMessage());
        }
    }

    private function testResponseParsingNullErrorHandling()
    {
        echo "\nTest Suite 12: Response Parsing with Non-JSON / Null Error Handling (PHP 8.x)\n";
        $response = new Response();
        $response->okay = true;
        $response->forApi = true;
        $response->body = "<html>502 Bad Gateway</html>";

        try {
            $response->wrapUp();
            $this->assert(false, "Non-JSON response triggers ApiException cleanly", "Did not throw exception");
        } catch (ApiException $ae) {
            $this->assert(true, "Non-JSON response triggers ApiException cleanly (no PHP 8 fatal TypeError)");
        } catch (\Throwable $e) {
            $this->assert(false, "Non-JSON response triggers ApiException cleanly", get_class($e) . ': ' . $e->getMessage());
        }
    }

    private function testResponseHelpers()
    {
        echo "\nTest Suite 13: Response Status Helpers (isSuccessful, isPending)\n";
        $successObj = (object)['responseCode' => '00'];
        $pendingObj = (object)['responseCode' => '02'];
        $failedObj  = (object)['responseCode' => '01'];

        $this->assert(Rexpay::isSuccessful($successObj) === true, "isSuccessful() returns true for responseCode '00'");
        $this->assert(Rexpay::isSuccessful($failedObj) === false, "isSuccessful() returns false for failed response");
        $this->assert(Rexpay::isPending($pendingObj) === true, "isPending() returns true for responseCode '02'");
        $this->assert(Rexpay::isPending($successObj) === false, "isPending() returns false for successful response");
    }

    private function testWebhookEventSignature()
    {
        echo "\nTest Suite 14: Webhook Event Signature Verification\n";
        $rawPayload = json_encode(['event' => 'charge.success', 'data' => ['reference' => 'REF_001']]);
        $secretKey = 'rexpay_secret_key_12345';
        $validSignature = hash_hmac('sha512', $rawPayload, $secretKey);

        $reflection = new \ReflectionClass(Event::class);
        $event = $reflection->newInstanceWithoutConstructor();
        
        $rawProp = $reflection->getProperty('raw');
        $rawProp->setAccessible(true);
        $rawProp->setValue($event, $rawPayload);

        $sigProp = $reflection->getProperty('signature');
        $sigProp->setAccessible(true);
        $sigProp->setValue($event, $validSignature);

        $this->assert($event->validFor($secretKey) === true, "Valid HMAC-SHA512 webhook signature verified");
        $this->assert($event->validFor('wrong_key') === false, "Invalid secret key rejected");
    }

    private function testFeeCalculation()
    {
        echo "\nTest Suite 15: Fee Calculator\n";
        $fee = new Fee();
        $calculated = $fee->calculateFor(100000);
        $this->assert($calculated === 1500, "Fee calculateFor 1000 NGN is 15 NGN (1500 kobo)");
    }
}

$runner = new TestRunner();
$runner->run();
