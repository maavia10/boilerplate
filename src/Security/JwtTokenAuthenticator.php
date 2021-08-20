<?php

namespace App\Security;

use App\Entity\User;
use Doctrine\ORM\EntityManager;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\ExpiredTokenException;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\JWTDecodeFailureException;
use Lexik\Bundle\JWTAuthenticationBundle\TokenExtractor\AuthorizationHeaderTokenExtractor;
use Lexik\Bundle\JWTAuthenticationBundle\TokenExtractor\QueryParameterTokenExtractor;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Guard\AbstractGuardAuthenticator;

class JwtTokenAuthenticator extends AbstractGuardAuthenticator
{
    private $container;
    private $jwtEncoder;

    protected static $allowed_route = 'allowed_route';
    public function __construct(JWTEncoderInterface $jwtEncoder, Container $container)
    {
        $this->container = $container;
        $this->jwtEncoder = $jwtEncoder;
    }


    /**
     * Called on every request. Return whatever credentials you want,
     * or null to stop authentication.
     */
    public function getCredentials(Request $request)
    {

        $token = $request->headers->get('X-Auth-Token');
        if ($token) {
            return array('token' => $token);
        }

        $extractor = new AuthorizationHeaderTokenExtractor('Bearer', 'AuthorizationUserToken');
        $token = $extractor->extract($request);


        if (!$token) {
            $extractor = new QueryParameterTokenExtractor('bearer');
            $token = $extractor->extract($request);
            if (!$token) {


                throw new \Symfony\Component\Security\Core\Exception\BadCredentialsException('Authorization field required!', 411);
            }
        }


        return array(
            'token' => $token,
        );

    }

    public function getUser($credentials, UserProviderInterface $userProvider)
    {

        try {

            if($credentials['token'] == self::$allowed_route){
                return null;
            }
            $data = $this->jwtEncoder->decode($credentials['token']);
        } catch (\Exception $e) {


            $errorCode = 402;
            switch ($e->getReason()){
                case 'expired_token':{

                    $errorCode = 421;
                }
            }

            throw new \Symfony\Component\Security\Core\Exception\BadCredentialsException('We\'ve upgraded our systems and servers to improve your experience. Please login again for a new and improved Core Direction.', $errorCode, $e);
        }

        if (!$data) {
            return;
        }

        /**
         * @var $user User
         */
        $user = $this->container->get('doctrine.orm.default_entity_manager')->getRepository(User::class)
            ->findOneBy(array('token' => $data['auth_token']));

            if(!$user){

                throw new \Symfony\Component\Security\Core\Exception\BadCredentialsException('inavlid Token');
            }
        return $user;


    }

    public function checkCredentials($credentials, UserInterface $user)
    {

        return true;
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, $providerKey)
    {

        // on success, let the request continue
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception)
    {
        $errorCode = 423;
        if($exception->getCode() == 421){

            $errorCode = $exception->getCode();
        }

        return new JsonResponse(array(
            'error'=>'invalid token'
        ),400);

    }

    /**
     * Called when authentication is needed, but it's not sent.
     */
    public function start(Request $request, AuthenticationException $authException = null)
    {

        return new JsonResponse(array(
            "result" => 'Missing credentials'
        ),400);

    }

    public function supportsRememberMe()
    {
        return false;
    }





    /**
     * @inheritDoc
     */
    public function supports(Request $request)
    {

        if (in_array($request->getRequestUri(), [
            '/api/login'
        ])) {

            return false;
        }
        return true;
    }
}