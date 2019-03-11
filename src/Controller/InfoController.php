<?php

namespace Adshares\Aduser\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class InfoController extends AbstractController
{
    public function index()
    {
        return new Response('<h1>' . getenv('ADUSER_NAME') . ' v' . getenv('ADUSER_VERSION') . '</h1>');
    }

    public function info(Request $request)
    {
        $info = [
            'module' => 'aduser',
            'name' => getenv('ADUSER_NAME'),
            'version' => getenv('ADUSER_VERSION'),
            'pixelUrl' => str_replace(['_:', ':_', '.html'], ['{', '}', '.{format}'], $this->generateUrl(
                'pixel_register',
                [
                    'adserver' => '_:adserver:_',
                    'user' => '_:user:_',
                    'nonce' => '_:nonce:_',
                    '_format' => 'html'
                ],
                UrlGeneratorInterface::ABSOLUTE_URL)),
            'supportedFormats' => ['gif', 'html'],
            'privacyUrl' => $this->generateUrl('privacy', [], UrlGeneratorInterface::ABSOLUTE_URL),
        ];

        return new Response(
            $request->getRequestFormat() === 'txt' ?
                self::formatTxt($info) :
                self::formatJson($info)
        );
    }

    public function privacy()
    {
        return new Response('<h1>Privacy</h1>');
    }

    private static function formatJson(array $data)
    {
        return json_encode($data);
    }

    private static function formatTxt(array $data)
    {
        $response = '';
        foreach ($data as $key => $value) {
            $key = strtoupper(preg_replace('([A-Z])', '_$0', $key));
            if (is_array($value)) {
                $value = implode(',', $value);
            }
            if (strpos($value, ' ') !== false) {
                $value = '"' . $value . '"';
            }
            $response .= sprintf("%s=%s\n", $key, $value);
        }

        return $response;
    }
}