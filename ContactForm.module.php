<?php

namespace ProcessWire;

class ContactForm extends Process implements ConfigurableModule
{
    public static function getModuleinfo()
    {
        return [
            'title' => 'Contact Form',
            'summary' => 'Contact form module',
            'version' => 1,
            'installs' => ['ContactFormAdmin']
        ];
    }

    public function install()
    {
        $superAdminRole = $this->roles->get('superuser');

        if (!$this->fields->get('cf_name')) {
            $f            = new Field();
            $f->type      = $this->modules->get("FieldtypeText");
            $f->name      = 'cf_name';
            $f->precision = 2;
            $f->label     = 'Contact form name';
            $f->save();
        }

        if (!$this->fields->get('cf_email')) {
            $f = new Field();
            $f->type = $this->modules->get("FieldtypeText");
            $f->name = 'cf_email';
            $f->label = 'Contact form email';
            $f->save();
        }

        if (!$this->fields->get('cf_message')) {
            $f = new Field();
            $f->type = $this->modules->get("FieldtypeTextarea");
            $f->name = 'cf_message';
            $f->label = 'Contact form message';
            $f->save();
        }


        $fg = new Fieldgroup();
        $fg->name = 'cf_list_fields';
        $fg->add($this->fields->get('title'));
        $fg->add($this->fields->get('cf_name'));
        $fg->add($this->fields->get('cf_email'));
        $fg->add($this->fields->get('cf_message'));
        $fg->save();

        $template = new Template();
        $template->name = 'cf_list';
        $template->fieldgroup = $fg;
        $template->allowPageNum = true;
        $template->roles = $superAdminRole;
        $template->save();
    }

    public function uninstall()
    {
        $template = $this->templates->get("cf_list");
        $page = $this->pages->get("name=cf_page");

        if ($template->getNumPages() > 0) {
            throw new WireException("Can't uninstall because template been used by some pages.");
        } else {
            $this->wire('pages')->delete($page);
            $this->wire('templates')->delete($template);
            $this->wire('fieldgroups')->delete($this->fieldgroups->get('cf_list_fields'));
            $this->wire('fields')->delete($this->fields->get('cf_name'));
            $this->wire('fields')->delete($this->fields->get('cf_email'));
            $this->wire('fields')->delete($this->fields->get('cf_message'));
            $this->wire('modules')->uninstall('ContactFormAdmin');
        }
    }

    protected function ___renderForm()
    {
        $input = $this->wire('input');
        $form = $this->buildForm();
        $session = $this->wire('session');

        if ($input->post('cf_submit')) {
            $send = $this->processForm($form);

            if ($send) {
                $session->set('cf_sent', 1);
                $session->redirect($this->wire('page')->url() . '?sent=1');
            }
        }

        if ($input->get('sent') && $session->get('cf_sent')) {
            $session->remove('cf_sent');
            return $this->success_message;
        }

        return $form->render();
    }

    protected function ___processForm(InputfieldForm $form)
    {
        $session = $this->wire('session');

        $session->CSRF->validate();
        $form->processInput($this->wire('input')->post);
        $nameField = $form->getChildByName('name');
        $emailField = $form->getChildByName('email');
        $messageField = $form->getChildByName('message');
        $name = $nameField->attr('value');
        $email = $emailField->attr('value');
        $message = $messageField->attr('value');

        $page = new Page();
        $page->template = 'cf_list';
        $page->title = "Message from {$name}";
        $page->cf_name = $name;
        $page->cf_email = $email;
        $page->cf_message = $message;
        $cfPages = $this->pages->get("name=cf_page");
        $page->parent = $cfPages;
        $page->save();

        $this->sendEmail($name, $email, $message);
        return true;
    }

    private function sendEmail($name, $email, $message)
    {
        if ($this->send_email) {
            $mail = wireMail();
            $mail->to($this->send_email_to);
            $mail->from = $email;
            $mail->subject("New message from {$name} (contact form)");
            $mail->body($message);

            $mail->send();
        }
    }

    public function getModuleConfigInputfields(InputfieldWrapper $inputfields)
    {
        $f = $this->modules->get('InputfieldCheckbox');
        $f->label = $this->_('Send incoming message to email?');
        $f->name = 'send_email';
        $f->checked = $this->send_email;
        $inputfields->add($f);

        $f = $this->modules->get('InputfieldText');
        $f->label = 'Send incoming message to email address';
        $f->type = 'email';
        $f->showIf = 'send_email=1';
        $f->requiredIf = 'send_email=1';
        $f->name = 'send_email_to';
        $f->value = $this->send_email_to;
        $inputfields->add($f);

        $f = $this->modules->get('InputfieldText');
        $f->label = 'Success message';
        $f->type = 'text';
        $f->name = 'success_message';
        $f->value = $this->success_message ?: 'Message already sent.';
        $inputfields->add($f);
    }

    protected function ___buildForm()
    {
        $form = $this->modules->get('InputfieldForm');
        $form->attr('id', 'ContactUsForm');
        $form->description = $this->_('Contact Us');

        $nameField = $this->modules->get('InputfieldText');
        $nameField->set('label', $this->_('Name')); // Login form: password field label
        $nameField->attr('id+name', 'name');
        $nameField->attr('type', 'text');
        $nameField->attr('class', $this->className() . 'Name');
        $nameField->collapsed = Inputfield::collapsedNever;
        $nameField->required = true;
        $form->add($nameField);

        $emailField = $this->modules->get('InputfieldText');
        $emailField->set('label', $this->_('Email')); // Login form: password field label
        $emailField->attr('id+name', 'email');
        $emailField->attr('type', 'email');
        $emailField->attr('class', $this->className() . 'Email');
        $emailField->collapsed = Inputfield::collapsedNever;
        $emailField->required = true;
        $form->add($emailField);

        $messageField = $this->modules->get('InputfieldTextarea');
        $messageField->set('label', $this->_('Message'));
        $messageField->attr('id+name', 'message');
        $messageField->attr('class', $this->className() . 'Message');
        $messageField->collapsed = Inputfield::collapsedNever;
        $messageField->required = true;
        $form->add($messageField);

        /** @var InputfieldSubmit $submitField */
        $submitField = $this->modules->get('InputfieldSubmit');
        $submitField->attr('name', 'cf_submit');
        $submitField->attr('value', $this->_('Send'));
        $submitField->appendMarkup = $this->wire('session')->CSRF->renderInput();
        $form->add($submitField);
        return $form;
    }
}
