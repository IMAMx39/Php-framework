<?php

declare(strict_types=1);

namespace Framework\Mail;

/**
 * Représente un email à envoyer.
 *
 * Usage :
 *   $message = (new Message())
 *       ->from('noreply@example.com', 'Mon App')
 *       ->to('user@example.com', 'Jean Dupont')
 *       ->subject('Bienvenue !')
 *       ->html('<h1>Bonjour Jean !</h1>')
 *       ->text('Bonjour Jean !');
 */
class Message
{
    private ?Address  $from    = null;
    /** @var Address[] */
    private array     $to      = [];
    /** @var Address[] */
    private array     $cc      = [];
    /** @var Address[] */
    private array     $bcc     = [];
    private string    $subject = '';
    private ?string   $text    = null;
    private ?string   $html    = null;
    /** @var array<string, string> nom => chemin */
    private array     $attachments = [];

    // ------------------------------------------------------------------
    // Expéditeur
    // ------------------------------------------------------------------

    public function from(string $email, string $name = ''): static
    {
        $this->from = new Address($email, $name);

        return $this;
    }

    public function getFrom(): ?Address
    {
        return $this->from;
    }

    // ------------------------------------------------------------------
    // Destinataires
    // ------------------------------------------------------------------

    public function to(string $email, string $name = ''): static
    {
        $this->to[] = new Address($email, $name);

        return $this;
    }

    /** @return Address[] */
    public function getTo(): array
    {
        return $this->to;
    }

    public function cc(string $email, string $name = ''): static
    {
        $this->cc[] = new Address($email, $name);

        return $this;
    }

    /** @return Address[] */
    public function getCc(): array
    {
        return $this->cc;
    }

    public function bcc(string $email, string $name = ''): static
    {
        $this->bcc[] = new Address($email, $name);

        return $this;
    }

    /** @return Address[] */
    public function getBcc(): array
    {
        return $this->bcc;
    }

    // ------------------------------------------------------------------
    // Sujet
    // ------------------------------------------------------------------

    public function subject(string $subject): static
    {
        $this->subject = $subject;

        return $this;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    // ------------------------------------------------------------------
    // Corps
    // ------------------------------------------------------------------

    public function text(string $content): static
    {
        $this->text = $content;

        return $this;
    }

    public function getText(): ?string
    {
        return $this->text;
    }

    public function html(string $content): static
    {
        $this->html = $content;

        return $this;
    }

    public function getHtml(): ?string
    {
        return $this->html;
    }

    // ------------------------------------------------------------------
    // Pièces jointes
    // ------------------------------------------------------------------

    /**
     * @param string $path Chemin absolu vers le fichier.
     * @param string $name Nom d'affichage dans l'email.
     */
    public function attach(string $path, string $name = ''): static
    {
        $this->attachments[$name !== '' ? $name : basename($path)] = $path;

        return $this;
    }

    /** @return array<string, string> */
    public function getAttachments(): array
    {
        return $this->attachments;
    }

    // ------------------------------------------------------------------
    // Validation basique
    // ------------------------------------------------------------------

    public function validate(): void
    {
        if ($this->from === null) {
            throw new \LogicException('Le champ From est obligatoire.');
        }

        if (empty($this->to)) {
            throw new \LogicException('Au moins un destinataire (To) est requis.');
        }

        if ($this->subject === '') {
            throw new \LogicException('Le sujet est obligatoire.');
        }

        if ($this->text === null && $this->html === null) {
            throw new \LogicException('Le corps du message (text ou html) est obligatoire.');
        }
    }
}
