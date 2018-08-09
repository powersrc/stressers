<?php

namespace App\Command;

use App\Support\Arr;
use App\Support\ExitCodes;
use App\Support\Str;
use App\Support\Type;
use Exception;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\LengthRequiredHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotAcceptableHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\PreconditionFailedHttpException;
use Symfony\Component\HttpKernel\Exception\PreconditionRequiredHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;
use Symfony\Component\HttpKernel\KernelInterface;
use Throwable;

class CurlStressCommand extends Command
{
    /** @var OutputInterface */
    private $output;

    /** @var InputInterface */
    private $input;

    /** @var KernelInterface */
    private $kernel;

    public function __construct(KernelInterface $kernel)
    {
        parent::__construct();

        $this->kernel = $kernel;
    }

    protected function configure()
    {
        $this->
            setName('stress:curl')->
            setDescription('Stresses the system using the CURL.')->
            setHelp('Stresses the system using the CURL library, through Guzzle.');

        $this->addArgument('cycles', InputArgument::OPTIONAL, '', 1024);
        $this->addOption('https', 'S', InputOption::VALUE_NONE);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->output = $output;
        $this->input  = $input;
        $formatter = $output->getFormatter();
        $formatter->setStyle('h1',
            new OutputFormatterStyle('yellow', null, ['bold', 'underscore']));
        $formatter->setStyle('h2',
            new OutputFormatterStyle('yellow', null, ['bold']));
        $formatter->setStyle('h3',
            new OutputFormatterStyle('yellow'));
        $formatter->setStyle('success',
            new OutputFormatterStyle('green'));
        $formatter->setStyle('failure',
            new OutputFormatterStyle('white', 'red'));

        return $this->main();
    }

    private function main(): int
    {
        $this->output->writeln('<h1># Memory Stress using cURL</h1>');

        $filename = $this->input->getOption('https') ? '/../data/https_urls.txt' : '/../data/http_urls.txt';

        /** @var string[]|bool $urls */
        $urls = Arr::map(file($this->kernel->getRootDir() . $filename), function ($url) {
            return Str::trim($url);
        });

        if ($urls === false) {
            return 1;
        }

        $cycles = $this->input->getArgument('cycles');
        for ($i = 0; $i !== $cycles; ++$i) {
            $this->output->writeln("<h2>## Iteration $i</h2>");
            foreach ($urls as $url) {
                $this->download($url);
            }
        }

        return ExitCodes::SUCCESS;
    }

    private function download(string $url): void
    {
        $this->output->write("- $url - ");
        try {

            $client = new HttpClient([ RequestOptions::HTTP_ERRORS => false ]);

            $response = self::checkResponse($client->get($url));
            $contentType = Arr::head($response->getHeader('Content-Type'));
            $contentSize = self::formatSize(Arr::head($response->getHeader('Content-Length')));

            $result = 'Done';
            if ($contentType !== null) {
                $result .= $contentSize !== null ? ": $contentType ($contentSize)" : ": $contentType";
            } elseif ($contentSize !== null) {
                $result .= ": ($contentSize)";
            }

            $this->output->writeln("<success>$result</success>");
        } catch (Throwable $e) {
            $class = Type::getClassNameOf($e);
            $message = $e->getMessage();

            $this->output->writeln("<failure>$class: $message</failure>");
        } finally {
            unset($client);
        }
    }

    private static function checkResponse(ResponseInterface $response): ResponseInterface
    {
        $status= $response->getStatusCode();
        if ($status >= 300) {
            throw self::createHttpErrorFor($response);
        }

        return $response;
    }

    /**
     * Creates the appropriate exception based on an HTTP status code.
     *
     * @param int            $status
     * @param string|null    $message
     * @param Exception|null $previous
     * @param int            $code
     * @param array          $headers
     *
     * @return HttpException
     */
    public static function createHttpError(int $status, ?string $message = null, ?Exception $previous = null, int $code = 0, array $headers = []): HttpException
    {
        switch ($status) {
            case 400:
                return new BadRequestHttpException($message, $previous, $code, $headers);
            case 401:
                return new UnauthorizedHttpException($headers['WWW-Authenticate'] ?? 'Bearer', $message, $previous, $code, $headers);
            case 403:
                return new AccessDeniedHttpException($message, $previous, $code, $headers);
            case 404:
                return new NotFoundHttpException($message, $previous, $code, $headers);
            case 405:
                return new MethodNotAllowedHttpException(key_exists('Allow', $headers) ?
                    explode(',', $headers['Allow']) : [], $message, $previous, $code, $headers);
            case 406:
                return new NotAcceptableHttpException($message, $previous, $code, $headers);
            case 409:
                return new ConflictHttpException($message, $previous, $code, $headers);
            case 410:
                return new GoneHttpException($message, $previous, $code, $headers);
            case 411:
                return new LengthRequiredHttpException($message, $previous, $code, $headers);
            case 412:
                return new PreconditionFailedHttpException($message, $previous, $code, $headers);
            case 415:
                return new UnsupportedMediaTypeHttpException($message, $previous, $code, $headers);
            case 422:
                return new UnprocessableEntityHttpException($message, $previous, $code, $headers);
            case 428:
                return new PreconditionRequiredHttpException($message, $previous, $code, $headers);
            case 429:
                return new TooManyRequestsHttpException($headers['Retry-After'] ?? null, $message, $previous, $code, $headers);
            case 503:
                return new ServiceUnavailableHttpException($headers['Retry-After'] ?? null, $message, $previous, $code, $headers);
            default:
                return new HttpException($status, $message, $previous, $headers, $code);
        }
    }

    /**
     * Creates the appropriate exception based on an HTTP message response.
     *
     * @param ResponseInterface $response
     * @param null|string       $message
     * @param int|null          $status
     * @param Exception|null    $previous
     * @param int               $code
     *
     * @return HttpException
     */
    public static function createHttpErrorFor(ResponseInterface $response, ?string $message = null, ?int $status = null, ?Exception $previous = null, int $code = 0): HttpException
    {
        $status  = $status ?? $response->getStatusCode();
        $message = $message ?? $response->getReasonPhrase();
        return self::createHttpError($status, $message, $previous, $code, $response->getHeaders());
    }

    /**
     * @param int|float|null $number
     *
     * @return null|string
     */
    private static function formatSize($number): ?string
    {
        if ($number === null) {
            return null;
        }

        $number = (float) $number;
        $unit   = $number !== 1.0 ? 'bytes' : 'byte';

        if ($number >= 1024.0) {
            $number /= 1024.0;
            $unit   =  'KiB';
        }

        if ($number >= 1024.0) {
            $number /= 1024.0;
            $unit   =  'MiB';
        }

        if ($number >= 1024.0) {
            $number /= 1024.0;
            $unit   =  'GiB';
        }

        $number = round($number, 1);

        return "$number $unit";
    }

    /**
     * @param ResponseInterface $response
     * @param string            $header
     *
     * @return string
     */
    private static function getHeaderHead(ResponseInterface $response, string $header): string
    {
        $values = $response->getHeader($header);

        return count($values) !== 0 ? self::strBefore($values[0], ';') : null;
    }

    /**
     * @param string $value
     * @param string $stop
     *
     * @return string
     */
    private static function strBefore(string $value, string $stop): string
    {
        $pos = strpos($value, $stop);

        return $pos !== false ? substr($value, 0, $pos) : $value;
    }
}
