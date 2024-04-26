<?php

/**
 * Add an attribute for multiple choice questions too remove irrelevant answers
 *
 * @author Jurgen Doll
 * @copyright 2024
 * @license GPL
 * @version 1.0.0
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */

class removeIrrelevantAnswers extends PluginBase
{
    protected static $name = "removeIrrelevantAnswers";
    protected static $description = "Allow to remove irrelevant subquestions directly";

    public function init()
    {
        $this->subscribe( "beforeActivate" );
        $this->subscribe( "beforeQuestionRender", "removeIrrelevantAnswersInList" );
        $this->subscribe( "newQuestionAttributes" );
    }

    public function beforeActivate()
    {
        if ( ! $this->getEvent() ) throw new CHttpException(403);

        $domDocumentPlugin = Plugin::model()->find( "name=:name", array( ":name" => "toolsDomDocument" ) );
        
        if ( ! $domDocumentPlugin )
        {
            $this->getEvent()->set( "message", gT("You must install the toolsDomDocument plugin") );
            // https://gitlab.com/SondagesPro/coreAndTools/toolsDomDocument
            $this->getEvent()->set( "success", false );
        }
        elseif ( ! $domDocumentPlugin->active )
        {
            $this->getEvent()->set( "message", gT("You must activate the toolsDomDocument plugin") );
            $this->getEvent()->set( "success", false );
        }
    }

    /**
     * Add a new removeIrrelevantAnswers attribute to certain question types.
     */
    public function newQuestionAttributes()
    {
        $oEvent = $this->getEvent();

        if ( $oEvent ) $oEvent->append( "questionAttributes", array
        (
            "removeIrrelevantAnswers" => array
            (
                "types" => "MPQ",
                "category" => gT('Logic'),
                "sortorder" => 5,
                "inputtype" => "switch",
                "default" => "0",
                "help" => "If a subquestion's relevance equation is false then it'll be removed instead of hidden or disabled. (Plugin)",
                "caption" => "Remove Irrelevant Answers"
            )
        ));
    }

    /**
     * Using beforeRenderQuestion event to remove irrelevant subquestions
     */
    public function removeIrrelevantAnswersInList()
    {
        $oEvent = $this->getEvent();

        if ( ! $oEvent || ! in_array( $oEvent->get("type"), array("M","P","Q") ) ) return;

        $aAttributes = QuestionAttribute::model()->getQuestionAttributes( $oEvent->get("qid") );

        if ( ! $aAttributes["removeIrrelevantAnswers"] ) return;

        // Convert HTML string fragment to HTML Document
        $dom = new \toolsDomDocument\SmartDOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->loadPartialHTML( $oEvent->get("answers") );
        $xpath = new DOMXpath( $dom );
        $changed = false;

        // Find <li> elements that have a class attribute containing ls-irrelevant
        foreach ( $xpath->query("//li[contains(@class, 'ls-irrelevant')]") as $item )
        {
            $item->parentNode->removeChild( $item );
            $changed = true;
        }

        if ( $changed )
        {
            // Converting HTML Document back to string fragment and removing dangling comments
            $comments = "/\s+<!-- Row \d+ -->\s+<!-- answer_row -->\s+<!-- end of answer_row -->\s/m";
            $oEvent->set( "answers", preg_replace( $comments, "", $dom->saveHTMLExact() ) );
        }
    }

}
