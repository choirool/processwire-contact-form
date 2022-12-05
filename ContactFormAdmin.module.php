<?php

namespace ProcessWire;

class ContactFormAdmin extends Process
{
    public static function getModuleinfo()
    {
        return [
            'title' => 'Contact Form Admin',
            'summary' => 'Contact form admin module',
            'version' => 1,
        ];
    }

    public function install()
    {
        $page = new Page();

        $page->template = "admin";
        $page->name = "cf_page";
        $page->title = "Contact form";
        $page->save();

        // set this module as the page process, this allows us to display the above
        $page->process = 'ContactFormAdmin';

        // get admin page and set as page parent
        $admin = $this->pages->get("id=2");
        $page->parent = $admin;

        // save page
        $page->save();
    }

    public function ___execute()
    {
        $messages = wire('pages')->find('template=cf_list,limit=10');
        $out = '';

        if ($messages->count()) {
            $table = $this->generateTableData($messages);
            $out .= $table->render();
        }
        $out .= $messages->renderPager();
        return $out;
    }

    private function generateTableData($messages)
    {
        $table = $this->modules->get('MarkupAdminDataTable');
        $table->headerRow(['Name', 'Email', 'Message', 'Send At', 'Action']);
        foreach ($messages as $message) {
            $table->row([
                $message->cf_name,
                $message->cf_email,
                $message->cf_message,
                $message->createdStr,
                'View' => "view?p={$message->id}"
            ]);
        }

        return $table;
    }
}
