<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SwiftmailerBundle\DataCollector;

use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * MessageDataCollector.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Clément JOBEILI <clement.jobeili@gmail.com>
 * @author Jérémy Romey <jeremy@free-agent.fr>
 */
class MessageDataCollector extends DataCollector
{
    private $container;

    /**
     * We don't inject the message logger and mailer here
     * to avoid the creation of these objects when no email are sent.
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * {@inheritdoc}
     */
    public function collect(Request $request, Response $response, \Exception $exception = null)
    {
        $this->reset();

        // only collect when Swiftmailer has already been initialized
        if (class_exists('Swift_Mailer', false)) {
            $mailers = $this->container->getParameter('swiftmailer.mailers');
            foreach ($mailers as $name => $mailer) {
                if ($this->container->getParameter('swiftmailer.default_mailer') == $name) {
                    $this->data['defaultMailer'] = $name;
                }
                $loggerName = sprintf('swiftmailer.mailer.%s.plugin.messagelogger', $name);
                if ($this->container->has($loggerName)) {
                    $logger = $this->container->get($loggerName);
                    $this->data['mailer'][$name] = array(
                        'messages' => $logger->getMessages(),
                        'messageCount' => $logger->countMessages(),
                        'isSpool' => $this->container->getParameter(sprintf('swiftmailer.mailer.%s.spool.enabled', $name)),
                    );
                    $this->data['messageCount'] += $logger->countMessages();
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function reset()
    {
        $this->data = array(
            'mailer' => array(),
            'messageCount' => 0,
            'defaultMailer' => '',
        );
    }

    /**
     * Returns the mailer names.
     *
     * @return array The mailer names.
     */
    public function getMailers()
    {
        return array_keys($this->data['mailer']);
    }

    /**
     * Returns the data collected of a mailer.
     *
     * @return array The data of the mailer.
     */
    public function getMailerData($name)
    {
        if (!isset($this->data['mailer'][$name])) {
            throw new \LogicException(sprintf('Missing "%s" data in "%s".', $name, get_class($this)));
        }

        return $this->data['mailer'][$name];
    }

    /**
     * Returns the message count of a mailer or the total.
     *
     * @return int The number of messages.
     */
    public function getMessageCount($name = null)
    {
        if (is_null($name)) {
            return $this->data['messageCount'];
        } elseif ($data = $this->getMailerData($name)) {
            return $data['messageCount'];
        }

        return;
    }

    /**
     * Returns the messages of a mailer.
     *
     * @return array The messages.
     */
    public function getMessages($name = 'default')
    {
        if ($data = $this->getMailerData($name)) {
            return $data['messages'];
        }

        return array();
    }

    /**
     * Returns if the mailer has spool.
     *
     * @return bool
     */
    public function isSpool($name)
    {
        if ($data = $this->getMailerData($name)) {
            return $data['isSpool'];
        }

        return;
    }

    /**
     * Returns if the mailer is the default mailer.
     *
     * @return bool
     */
    public function isDefaultMailer($name)
    {
        return $this->data['defaultMailer'] == $name;
    }

    public function extractAttachments(\Swift_Message $message)
    {
        $attachments = array();

        foreach ($message->getChildren() as $child) {
            if ($child instanceof \Swift_Attachment) {
                $attachments[] = $child;
            }
        }

        return $attachments;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'swiftmailer';
    }
}
