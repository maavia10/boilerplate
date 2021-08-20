<?php

namespace App\Controller;

use App\Entity\Component;
use App\Entity\User;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class ServicesController extends AbstractController
{

    protected $encoder;
    protected $jwtEncoder;
    public function __construct(UserPasswordEncoderInterface $encoder,JWTEncoderInterface $JWTEncoder)
    {
        $this->encoder = $encoder;
        $this->jwtEncoder = $JWTEncoder;
    }

    /**
     * @Route("/api/login", name="api_login")
     */
    public function index(Request  $request): Response
    {

        $content = $request->getContent();
        $data = json_decode($content,1);

        if(empty($data['email']) || empty($data['password']) ){

            return  new JsonResponse(array(
                'error'=> 'email or password is missing'
            ),400);

        }

        $em  = $this->getDoctrine()->getManager();

        /**
         * @var  $user User
         */
        $user = $em->getRepository(User::class)->findOneBy(array(
            'email'=>$data['email']
        ));

        if(!$user){

            return  new JsonResponse(array(
                'error'=> 'invlaid email provided'
            ),400);
        }

        $passwordCheck = $this->encoder->isPasswordValid($user,$data['password']);

        if(!$passwordCheck){

            return  new JsonResponse(array(
                'error'=> 'invlaid password provided'
            ),400);
        }

        $token =  rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');

        $user->setToken($token);

        $em->persist($user);
        $em->flush();

        $jwtToken = $this->jwtEncoder->encode(array(
            'auth_token' => $token,
            'exp' => time() + 3600000
        ));


        return  new JsonResponse(array(
            'token'=> $jwtToken
        ));
    }

    /**
     * @Route("/api/get/components", name="api_get_components")
     */
    public function getAllCompoenents()
    {



        try {
            $user = $this->getUser();

            $em = $this->getDoctrine()->getManager();
            $components = $em->getRepository(Component::class)->getAllComponents();

            return  new JsonResponse($components);
        }catch (\Exception $exception){

            return new JsonResponse(array(
                'error'=> $exception->getMessage()
            ),$exception->getCode());
        }
    }
}
