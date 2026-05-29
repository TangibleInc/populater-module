<?php

declare(strict_types=1);

namespace Tangible\Populater\Tests\Unit\Support;

use Tangible\Populater\Support\DummyContent;

class DummyContentTest extends \WPTestCase
{
    public function test_course_content_includes_heading_and_body(): void
    {
        $content = DummyContent::course('LearnDash Course', 1);

        $this->assertStringContainsString('<h2>LearnDash Course 1</h2>', $content);
        $this->assertStringContainsString('What you will learn', $content);
        $this->assertStringContainsString('Tangible Populator', $content);
    }

    public function test_lesson_content_includes_outline(): void
    {
        $content = DummyContent::lesson('LifterLMS Lesson', 2);

        $this->assertStringContainsString('<h2>LifterLMS Lesson 2</h2>', $content);
        $this->assertStringContainsString('Lesson outline', $content);
    }

    public function test_topic_section_and_module_content_are_non_empty(): void
    {
        $this->assertNotSame('', strip_tags(DummyContent::topic('LearnDash Topic', 1)));
        $this->assertNotSame('', strip_tags(DummyContent::section('LifterLMS Section', 1)));
        $this->assertNotSame('', strip_tags(DummyContent::module('Tangible Module', 1)));
    }

    public function test_quiz_and_question_content_are_non_empty(): void
    {
        $this->assertStringContainsString('<h2>LearnDash Quiz 1</h2>', DummyContent::quiz('LearnDash Quiz', 1));
        $this->assertStringContainsString('<h2>LearnDash Question 1</h2>', DummyContent::question('LearnDash Question', 1));
    }

    public function test_excerpt_mentions_populator(): void
    {
        $excerpt = DummyContent::excerpt('course', 'Tangible Course', 3);

        $this->assertStringContainsString('Tangible Populator', $excerpt);
        $this->assertStringContainsString('Tangible Course 3', $excerpt);
    }
}
