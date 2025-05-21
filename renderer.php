<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * AMOS renderer class is defined here.
 *
 * @package     local_amos
 * @copyright   2010 David Mudrak <david.mudrak@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * AMOS renderer class
 */
class local_amos_renderer extends plugin_renderer_base {

    /**
     * Renders the stage
     *
     * @param local_amos\output\stage $stage
     * @return string
     */
    protected function render_stage(local_amos\output\stage $stage) {
        global $CFG;

        // Count the number of committable strings.
        $committable = 0;
        foreach ($stage->strings as $string) {
            if ($string->committable) {
                if ($string->nocleaning) {
                    $isdifferent = ($string->current !== $string->new);
                } else {
                    $isdifferent = (trim($string->current) !== trim($string->new));
                }

                if ($isdifferent) {
                    $committable++;
                }
            }
        }

        if (empty($stage->strings)) {
            $output = $this->heading(get_string('stagestringsnone', 'local_amos'), 2, 'main', 'numberofstagedstrings');

            $output .= html_writer::div(
                html_writer::link(
                    new moodle_url('/local/amos/view.php'),
                    get_string('translatortoolopen', 'local_amos'),
                    ['class' => 'btn btn-success']
                ),
                'stagetool simple opentranslator'
            );

            if ($stage->importform) {
                ob_start();
                $stage->importform->display();
                $formoutput = ob_get_contents();
                ob_end_clean();

                $output .= $this->collapsible_stage_tool(
                    get_string('importfile', 'local_amos'),
                    $formoutput,
                    $this->output->help_icon('importfile', 'local_amos')
                );
            }

            if ($stage->executeform) {
                ob_start();
                $stage->executeform->display();
                $formoutput = ob_get_contents();
                ob_end_clean();

                $output .= $this->collapsible_stage_tool(
                    get_string('script', 'local_amos'),
                    $formoutput,
                    $this->help_icon('script', 'local_amos')
                );
            }

        } else {
            $output = '';
            if (!empty($stage->stagedcontribution)) {
                $output .= $this->heading_with_help(get_string('contribstaged', 'local_amos', $stage->stagedcontribution),
                    'contribstagedinfo', 'local_amos');
            }
            $a = (object)['staged' => count($stage->strings), 'committable' => $committable];
            if ($committable) {
                $output .= $this->heading(get_string('stagestringssome', 'local_amos', $a), 2, 'main', 'numberofstagedstrings');
            } else {
                $output .= $this->heading(get_string('stagestringsnocommit', 'local_amos', $a), 2, 'main', 'numberofstagedstrings');
            }
            unset($a);

            if ($committable and $stage->cancommit) {
                $commitform = html_writer::div(
                    html_writer::tag('textarea', s($stage->presetmessage), [
                        'placeholder' => get_string('commitmessage', 'local_amos'),
                        'name' => 'message',
                        'rows' => 3,
                        'class' => 'form-control',
                        'required' => 'required'
                    ])
                );
                $commitform .= html_writer::empty_tag('input', ['name' => 'sesskey', 'value' => sesskey(), 'type' => 'hidden']);

                $commitform .= html_writer::div(
                    html_writer::checkbox('keepstaged', 1, false, get_string('commitkeepstaged', 'local_amos'), [],
                    ['class' => 'small px-1'])
                );

                $commitform .= html_writer::div(
                    html_writer::tag('button', get_string('commitbutton', 'local_amos'), [
                        'type' => 'submit',
                        'class' => 'btn btn-success'
                    ])
                );
                $commitform = html_writer::div($commitform, 'protected');
                $commitform = html_writer::tag('form', $commitform, [
                    'method' => 'post',
                    'action' => $CFG->wwwroot . '/local/amos/stage.php',
                ]);

                $output .= $this->collapsible_stage_tool(
                    get_string('commitstage', 'local_amos'),
                    $commitform,
                    $this->help_icon('commitstage', 'local_amos'),
                    true,
                    'commit'
                );
            }

            // Submit strings to language pack maintainers.
            if ($stage->canstash and $committable == 0) {
                $output .= html_writer::div(
                    html_writer::link(
                        new moodle_url('/local/amos/stage.php', ['submit' => 1, 'sesskey' => sesskey()]),
                        get_string('stagesubmit', 'local_amos'),
                        ['class' => 'btn btn-success']
                    ),
                    'stagetool simple'
                );
            }

            // Stage actions.
            $prunebutton = html_writer::link(
                new moodle_url('/local/amos/stage.php', ['prune' => 1, 'sesskey' => sesskey()]),
                '<i class="fa fa-eraser"></i> '.get_string('stageprune', 'local_amos'),
                ['class' => 'btn btn-warning protected prune']
            );

            $rebasebutton = html_writer::link(
                new moodle_url('/local/amos/stage.php', ['rebase' => 1, 'sesskey' => sesskey()]),
                '<i class="fa fa-code-fork"></i> '.get_string('stagerebase', 'local_amos'),
                ['class' => 'btn btn-warning protected rebase']
            );

            $unstageallbutton = html_writer::link(
                new moodle_url('/local/amos/stage.php', ['unstageall' => 1, 'sesskey' => sesskey()]),
                '<i class="fa fa-eject"></i> '.get_string('stageunstageall', 'local_amos'),
                ['class' => 'btn btn-danger protected unstageall']
            );

            $downloadbutton = html_writer::link(
                new moodle_url('/local/amos/stage.php', ['download' => 1, 'sesskey' => sesskey()]),
                '<i class="fa fa-download"></i> '.get_string('stagedownload', 'local_amos'),
                ['class' => 'btn']
            );

            $params['flast'] = $stage->filterfields->flast;

            $i = 0;
            foreach ($stage->filterfields->flng as $flng) {
                $params['flng['.$i.']'] = $flng;
                $i++;
            }
            $i = 0;
            foreach ($stage->filterfields->fcmp as $fcmp) {
                $params['fcmp['.$i.']'] = $fcmp;
                $i++;
            }
            $params['fstg'] = 1;
            $params['__lazyform_amosfilter'] = 1;
            $params['sesskey'] = sesskey();

            $editbutton = html_writer::link(
                new moodle_url('/local/amos/view.php', $params),
                '<i class="fa fa-edit"></i> '.get_string('stageedit', 'local_amos'),
                ['class' => 'btn btn-info edit']
            );

            $actionbuttons = $editbutton.$rebasebutton;
            if ($committable) {
                $actionbuttons .= $prunebutton;
            }
            $actionbuttons .= $unstageallbutton;
            $actionbuttons .= $downloadbutton;
            $output .= $this->collapsible_stage_tool(
                get_string('stageactions', 'local_amos'),
                $actionbuttons,
                $this->help_icon('stageactions', 'local_amos'),
                false,
                'stageactions'
            );

            // Save work in progress.
            if ($stage->canstash) {
                $a = [
                    'time' => userdate(time(), get_string('strftimedaydatetime', 'langconfig'))
                ];
                $stashtitle = get_string('stashtitledefault', 'local_amos', $a);

                $stashform = html_writer::empty_tag('input', ['name' => 'sesskey', 'value' => sesskey(), 'type' => 'hidden']);
                $stashform .= html_writer::empty_tag('input', ['name' => 'new', 'value' => 1, 'type' => 'hidden']);
                $stashform .= html_writer::empty_tag('input', ['name' => 'name',
                                                                    'value' => $stashtitle,
                                                                    'placeholder' => get_string('stashtitle', 'local_amos'),
                                                                    'type' => 'text',
                                                                    'size' => 50,
                                                                    'id' => 'stashtitle',
                                                                    'class' => 'form-control',
                                                                    'maxlength' => 255]);
                $stashform .= html_writer::tag('button', '<i class="fa fa-fast-forward"></i> ' .
                    get_string('stashpush', 'local_amos'), ['type' => 'submit', 'class' => 'btn btn-light']);
                $stashform  = html_writer::div($stashform);
                $stashform  = html_writer::tag('form', $stashform, [
                    'method' => 'post',
                    'action' => $CFG->wwwroot . '/local/amos/stash.php',
                ]);
                $output .= $this->collapsible_stage_tool(
                    get_string('stashactions', 'local_amos'),
                    $stashform,
                    $this->help_icon('stashactions', 'local_amos'),
                    false,
                    'stashactions'
                );
            }

            // And finally the staged strings themselves.
            $output .= $this->render_from_template('local_amos/stage', $stage->export_for_template($this));
        }

        $output = html_writer::tag('div', $output, ['id' => 'amosstagewrapper']);

        return $output;
    }

    /**
     * Helper method for rendering collapsible tools at the stage page
     *
     * @param string $title
     * @param string $content
     * @param string $helpicon
     * @param bool $expanded
     * @param string $extraclasses
     * @return string
     */
    protected function collapsible_stage_tool($title, $content, $helpicon = '', $expanded = false, $extraclasses = '') {

        if ($expanded) {
            $attr = ['aria-expanded' => 'true'];
            $collapsed = '';
            $collapse = ' show';
        } else {
            $attr = ['aria-expanded' => 'false'];
            $collapsed = ' collapsed';
            $collapse = '';
        }

        $attr['aria-controls'] = html_writer::random_id('collapse_');
        $attr['data-bs-toggle'] = 'collapse';
        $attr['data-bs-target'] = '#'.$attr['aria-controls'];

        $output = html_writer::start_div('stagetool '.$extraclasses);
        $output .= html_writer::div(
            html_writer::span($title, 'stagetool-title').html_writer::span($helpicon, 'stagetool-helpicon'),
            'stagetool-heading'.$collapsed, $attr
        );
        $output .= html_writer::div($content, 'stagetool-content collapse'.$collapse, ['id' => $attr['aria-controls']]);
        $output .= html_writer::end_div();

        return $output;
    }

    /**
     * Returns formatted commit date and time
     *
     * In our git repos, timestamps are stored in UTC always and that is what standard git log
     * displays.
     *
     * @param int $timestamp
     * @return string formatted date and time
     */
    public static function commit_datetime($timestamp) {
        $tz = date_default_timezone_get();
        date_default_timezone_set('UTC');
        $t = date('Y-m-d H:i e', $timestamp);
        date_default_timezone_set($tz);
        return $t;
    }

    /**
     * Render stash information
     *
     * @param local_amos_stash $stash
     * @return string to be echo'ed
     */
    protected function render_local_amos_stash(local_amos_stash $stash) {

        $output  = html_writer::start_tag('div', ['class' => 'stash']);
        if ($stash->isautosave) {
            $output .= html_writer::tag('h3', get_string('stashautosave', 'local_amos'));
            $extraclasses = ' autosave';
        } else {
            $output .= html_writer::tag('h3', s($stash->name));
            $extraclasses = '';
        }
        $output .= $this->output->user_picture($stash->owner, [
            'includefullname' => true,
            'class' => 'my-2 mr-2 d-inline-block',
            'size' => 21,
        ]);

        if ($stash->timemodified) {
            $output .= html_writer::tag('div', userdate($stash->timemodified, get_string('strftimedaydatetime', 'langconfig')),
            ['class' => 'timemodified']);
        } else {
            $output .= html_writer::tag('div', userdate($stash->timecreated, get_string('strftimedaydatetime', 'langconfig')),
            ['class' => 'timecreated']);
        }
        $output .= html_writer::tag('div', get_string('stashstrings', 'local_amos', $stash->strings),
                                    ['class' => 'strings']);
        $output .= html_writer::tag('div', get_string('stashlanguages', 'local_amos', s(implode(', ', $stash->languages))),
                                    ['class' => 'languages']);
        $output .= html_writer::tag('div', get_string('stashcomponents', 'local_amos', s(implode(', ', $stash->components))),
                                    ['class' => 'components']);

        $output .= html_writer::end_tag('div');

        $actions = '';
        foreach ($stash->get_actions() as $action) {
            $actions .= $this->output->single_button($action->url, $action->label, 'post', [
                'class' => 'singlebutton ' . $action->id,
            ]);
        }
        if ($actions) {
            $actions .= $this->output->help_icon('ownstashactions', 'local_amos');
            $actions = html_writer::tag('div', $actions, ['class' => 'actions']);
        }
        $output = $this->output->box($output . $actions, 'generalbox stashwrapper'.$extraclasses);

        return $output;
    }

    /**
     * Render single contribution record
     *
     * @param local_amos_contribution $contrib
     * @return string
     */
    protected function render_local_amos_contribution(local_amos_contribution $contrib) {
        global $USER;

        $output = '';
        $output .= $this->output->heading('#'.$contrib->info->id.' '.s($contrib->info->subject), 3, 'subject');
        $output .= $this->output->container($this->output->user_picture($contrib->author) . fullname($contrib->author), 'author');

        if ($contrib->info->timemodified) {
            $output .= $this->output->container(userdate($contrib->info->timemodified,
            get_string('strftimedaydatetime', 'langconfig')), 'timemodified');
        } else {
            $output .= $this->output->container(userdate($contrib->info->timecreated,
            get_string('strftimedaydatetime', 'langconfig')), 'timecreated');
        }

        $output .= $this->output->container(format_text($contrib->info->message), 'message');
        $output = $this->box($output, 'generalbox source');

        $table = new html_table();
        $table->attributes['class'] = 'generaltable details';

        $row = new html_table_row([
            get_string('contribstatus', 'local_amos'),
            get_string('contribstatus' . $contrib->info->status, 'local_amos') .
            $this->output->help_icon('contribstatus', 'local_amos')
        ]);
        $row->attributes['class'] = 'status'.$contrib->info->status;
        $table->data[] = $row;

        if ($contrib->assignee) {
            $assignee = $this->output->user_picture($contrib->assignee, ['size' => 16]) . fullname($contrib->assignee);
        } else {
            $assignee = get_string('contribassigneenone', 'local_amos');
        }
        $row = new html_table_row([get_string('contribassignee', 'local_amos'), $assignee]);
        if ($contrib->assignee) {
            if ($contrib->assignee->id == $USER->id) {
                $row->attributes['class'] = 'assignment self';
            } else {
                $row->attributes['class'] = 'assignment';
            }
        } else {
            $row->attributes['class'] = 'assignment none';
        }
        $table->data[] = $row;

        // Display the contribution language.
        $listlanguages = mlang_tools::list_languages();

        if (!empty($listlanguages[$contrib->language])) {
            $languagename = $listlanguages[$contrib->language];
        } else {
            $languagename = $contrib->language;
        }

        $languagename = html_writer::span($languagename, 'languagename');

        if (has_capability('local/amos:changecontriblang', context_system::instance())) {
            $languagename .= ' '.$this->output->help_icon('contriblanguagechange', 'local_amos',
                get_string('contriblanguagewrong', 'local_amos'));
        } else {
            $languagename .= ' '.$this->output->help_icon('contriblanguagereport', 'local_amos',
                get_string('contriblanguagewrong', 'local_amos'));
        }

        $row = new html_table_row([get_string('contriblanguage', 'local_amos'), $languagename]);
        $table->data[] = $row;

        $row = new html_table_row([get_string('contribcomponents', 'local_amos'), $contrib->components]);
        $table->data[] = $row;

        $a = [
            'orig' => $contrib->strings,
            'new' => $contrib->stringsreb,
            'same' => ($contrib->strings - $contrib->stringsreb),
        ];

        if ($contrib->stringsreb == 0) {
            $s = get_string('contribstringsnone', 'local_amos', $a);

        } else if ($contrib->strings == $contrib->stringsreb) {
            $s = get_string('contribstringseq', 'local_amos', $a);

        } else {
            $s = get_string('contribstringssome', 'local_amos', $a);
        }

        $row = new html_table_row([get_string('contribstrings', 'local_amos'), $s]);
        $table->data[] = $row;

        $output .= html_writer::table($table);
        $output = $this->output->container($output, 'contributionwrapper');

        return $output;
    }

    /**
     * Renders the AMOS credits page
     *
     * The $editmode is interpreted as follows:
     * - false: the user does not have rights to edit this page
     * - null: the user can edit this page but the editing mode is off
     * - true: the editing mode is on
     *
     * @param array $people as populated in credits.php
     * @param string $currentlanguage the user's current language
     * @param bool|null $editmode
     * @return string
     */
    public function page_credits(array $people, $currentlanguage, $editmode = false) {

        $out = '';
        $out .= $this->output->container(get_string('creditsthanks', 'local_amos'), 'p-1 thanks');

        $out .= $this->output->container_start('small quicklinks p-2');
        $links = [];
        foreach ($people as $langcode => $langdata) {
            if ($langcode === $currentlanguage) {
                $attributes = ['class' => 'current'];
            } else {
                $attributes = null;
            }

            $links[] = html_writer::link(new moodle_url('#credits-language-'.$langcode),
                str_replace(' ', '&nbsp;', $langdata->langname), $attributes);
        }
        $out .= implode(' | ', $links);
        $out .= $this->output->container_end();

        foreach ($people as $langcode => $langdata) {
            $out .= $this->output->container_start('card mb-2', 'credits-language-'.$langcode);
            $out .= $this->output->container($this->output->heading($langdata->langname, 3, 'langname'), 'card-header');
            $out .= $this->output->container_start('card-body');

            $out .= $this->output->container_start('maintainers');
            if (empty($langdata->maintainers)) {
                $out .= $this->output->container(
                    get_string('creditsnomaintainer', 'local_amos', ['url' => 'https://docs.moodle.org/en/Translation']),
                    'alert alert-warning'
                );
            } else {
                $out .= $this->output->container(get_string('creditsmaintainedby', 'local_amos'),
                    'font-weight-bold py-1 maintainers-title');
                $out .= $this->output->container_start('d-flex align-items-stretch');
                foreach ($langdata->maintainers as $maintainer) {
                    $out .= $this->output->container_start('maintainer bg-light p-3 border mr-2 mb-2');
                    $out .= $this->output->user_picture($maintainer, ['size' => 50]);
                    $out .= $this->output->container(fullname($maintainer), 'fullname my-1');
                    $out .= $this->output->action_icon(
                        new moodle_url('/message/index.php', ['id' => $maintainer->id]),
                        new pix_icon('t/message', get_string('creditscontact', 'local_amos')),
                        null,
                        ['class' => 'action contact']
                    );
                    if ($editmode) {
                        $out .= $this->output->action_icon(
                            new moodle_url('/local/amos/admin/translators.php', [
                                'action' => 'del',
                                'status' => AMOS_USER_MAINTAINER,
                                'langcode' => $langcode,
                                'user' => $maintainer->id
                            ]),
                            new pix_icon('t/delete', get_string('remove')),
                            null,
                            ['class' => 'action delete']
                        );
                    }
                    $out .= $this->output->container_end();
                }
                $out .= $this->output->container_end();
            }
            if ($editmode) {
                $out .= html_writer::link(
                    new moodle_url('/local/amos/admin/translators.php', ['action' => 'add', 'status' => AMOS_USER_MAINTAINER,
                        'langcode' => $langcode]),
                    get_string('creditsaddmaintainer', 'local_amos'),
                    ['class' => 'btn btn-secondary m-1']
                );
            }
            $out .= $this->output->container_end();

            $out .= $this->output->container_start('contributors');
            if (!empty($langdata->contributors)) {
                $out .= $this->output->container(get_string('creditscontributors', 'local_amos'),
                    'contributors-title font-weight-bold py-1');
                foreach ($langdata->contributors as $contributor) {
                    $out .= $this->output->container_start('contributor d-inline-block border m-1 p-1 bg-light');
                    $out .= $this->output->user_picture($contributor, ['size' => 16, 'class' => 'd-inline-block m-1']);
                    $out .= $this->output->container(fullname($contributor), 'fullname d-inline-block');
                    if ($editmode and $contributor->iseditable) {
                        $out .= $this->output->action_icon(
                            new moodle_url('/local/amos/admin/translators.php', [
                                'action' => 'del',
                                'status' => AMOS_USER_CONTRIBUTOR,
                                'langcode' => $langcode,
                                'user' => $contributor->id
                            ]),
                            new pix_icon('t/delete', get_string('remove')),
                            null,
                            ['class' => 'action delete']
                        );
                    }
                    $out .= $this->output->container_end();
                }
            }

            if ($editmode) {
                $out .= html_writer::link(
                    new moodle_url('/local/amos/admin/translators.php', [
                        'action' => 'add',
                        'status' => AMOS_USER_CONTRIBUTOR,
                        'langcode' => $langcode,
                    ]),
                    get_string('creditsaddcontributor', 'local_amos'),
                    ['class' => 'btn btn-secondary m-1']
                );
            }

            $out .= $this->output->container_end();
            $out .= $this->output->container_end();
            $out .= $this->output->container_end();
        }

        return $out;
    }

    /**
     * Render problematic contributions from the credits.php page.
     *
     * @param array $issues
     * @return string
     */
    public function page_credits_issues(array $issues) {

        $out = '';

        $out = '<p><strong>There were some issues detected when checking for credits data</strong></p>';

        $out .= html_writer::start_tag('ul');
        foreach ($issues as $issue) {
            $out .= html_writer::tag('li', $issue->problem.' (userid '.$issue->record->id.')');
        }
        $out .= html_writer::end_tag('ul');
        $out = $this->output->notification($out);

        return $out;
    }

    /**
     * Makes sure there is a zero-width space after non-word characters in the given string
     *
     * This is used to wrap long strings like 'A,B,C,D,...,x,y,z' in the translator
     *
     * @link http://www.w3.org/TR/html4/struct/text.html#h-9.1
     * @link http://www.fileformat.info/info/unicode/char/200b/index.htm
     *
     * @param string $text plain text
     * @return string
     */
    public static function add_breaks($text) {

        debugging('local_amos_renderer::add_breaks() is deprecated. Use \\local_amos\\local\\util::add_breaks() instead.',
            DEBUG_DEVELOPER);

        return \local_amos\local\util::add_breaks($text);
    }
}
