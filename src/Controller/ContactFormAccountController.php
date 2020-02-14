<?php

declare(strict_types=1);

namespace MangoSylius\SyliusContactFormPlugin\Controller;

use Doctrine\ORM\EntityManagerInterface;
use MangoSylius\SyliusContactFormPlugin\Entity\ContactFormMessage;
use MangoSylius\SyliusContactFormPlugin\Entity\ContactFormMessageAnswer;
use MangoSylius\SyliusContactFormPlugin\Form\Type\ContactFormMessageAnswerType;
use MangoSylius\SyliusContactFormPlugin\Repository\ContactFormMessageAnswerRepository;
use MangoSylius\SyliusContactFormPlugin\Repository\ContactFormMessageRepository;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\ShopUser;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Templating\EngineInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class ContactFormAccountController
{
    /** @var EngineInterface */
    private $templatingEngine;
    /** @var TranslatorInterface */
    private $translator;
    /** @var EntityManagerInterface */
    private $entityManager;
    /** @var RouterInterface */
    private $router;
    /** @var FlashBagInterface */
    private $flashBag;
    /** @var FormFactoryInterface */
    private $builder;
    /** @var ChannelContextInterface */
    private $channelContext;
    /** @var ContactFormMessageRepository */
    private $contactFormMessageRepository;
    /** @var ContactFormMessageAnswerRepository */
    private $contactFormMessageAnswerRepository;
    /** @var TokenStorageInterface */
    private $token;

    public function __construct(
        EngineInterface $templatingEngine,
        TranslatorInterface $translator,
        EntityManagerInterface $entityManager,
        RouterInterface $router,
        FlashBagInterface $flashBag,
        FormFactoryInterface $builder,
        ChannelContextInterface $channelContext,
        ContactFormMessageRepository $contactFormMessageRepository,
        ContactFormMessageAnswerRepository $contactFormMessageAnswerRepository,
        TokenStorageInterface $tokenStorage
    ) {
        $this->templatingEngine = $templatingEngine;
        $this->translator = $translator;
        $this->entityManager = $entityManager;
        $this->router = $router;
        $this->flashBag = $flashBag;
        $this->builder = $builder;
        $this->channelContext = $channelContext;
        $this->contactFormMessageRepository = $contactFormMessageRepository;
        $this->contactFormMessageAnswerRepository = $contactFormMessageAnswerRepository;
        $this->token = $tokenStorage;
    }

    public function showAccountMessageAction(Request $request, int $id): Response
    {
        $token = $this->token->getToken();
        assert($token !== null);

        $shopUser = $token->getUser();
        assert($shopUser instanceof ShopUser);

        $customer = $shopUser->getCustomer();
        assert($customer instanceof CustomerInterface);
        $contactFormMessage = $this->contactFormMessageRepository->find($id);
        assert($contactFormMessage instanceof ContactFormMessage);

        if ($contactFormMessage->getCustomer() !== null && $customer->getId() !== $contactFormMessage->getCustomer()->getId()) {
            return new RedirectResponse($this->router->generate('mango_sylius_contact_form_shop_account_message_index'));
        }

        $contactFormMessageAnswer = new ContactFormMessageAnswer();
        $form = $this->builder->create(ContactFormMessageAnswerType::class, $contactFormMessageAnswer, [
            'method' => 'POST',
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $contactFormMessageAnswer->setCreatedAt(new \DateTime());
            $contactFormMessageAnswer->setContactFormMessage($contactFormMessage);

            $this->entityManager->persist($contactFormMessageAnswer);
            $this->entityManager->flush();

            $channel = $this->channelContext->getChannel();

            assert($channel instanceof ChannelInterface);
            $this->flashBag->add('success', $this->translator->trans('mango_contact_form_plugin.success'));

            return new RedirectResponse($this->router->generate('mango_sylius_contact_form_shop_account_message_show', ['id' => $id]));
        }

        return new Response($this->templatingEngine->render('@MangoSyliusContactFormPlugin/Shop/Account/show.html.twig', [
            'message' => $contactFormMessage,
            'answers' => $this->contactFormMessageAnswerRepository->findBy(['contactFormMessage' => $id]),
            'form' => $form->createView(),
        ]));
    }
}
