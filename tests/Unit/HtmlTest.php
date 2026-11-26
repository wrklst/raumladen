<?php

use App\Support\Html;

test('harmless markup stays, with a blank line still between paragraphs', function () {
    expect(Html::clean("<i>Rollrasen</i> (“sod”) is a series by <b>Zara</b>.\n\nSecond <a href=\"https://example.org/x?a=1&b=2\">paragraph</a>."))
        ->toBe("<i>Rollrasen</i> (“sod”) is a series by <b>Zara</b>.\n\nSecond <a href=\"https://example.org/x?a=1&amp;b=2\" rel=\"noopener noreferrer\">paragraph</a>.");
});

test('plain text is escaped and otherwise left alone', function () {
    expect(Html::clean("Tom & Jerry\n\n8.000 m²"))->toBe("Tom &amp; Jerry\n\n8.000 m²")
        ->and(Html::clean('  '))->toBeNull()
        ->and(Html::clean(null))->toBeNull();
});

test('nothing that could run code or load anything survives', function (string $dirty, string $clean) {
    expect(Html::clean($dirty))->toBe($clean);
})->with([
    'a script, with its content' => ['Hello <script>alert(1)</script>world', 'Hello world'],
    'an event handler' => ['<p onclick="alert(1)" style="color:red" class="x">Text</p>', '<p>Text</p>'],
    'a javascript link' => ['<a href="javascript:alert(1)">click</a>', '<a>click</a>'],
    'a javascript link in disguise' => ['<a href=" JaVaScRiPt:alert(1)">click</a>', '<a>click</a>'],
    'a data link' => ['<a href="data:text/html,<script>alert(1)</script>">x</a>', '<a>x</a>'],
    'a link to another site without a scheme' => ['<a href="//evil.example">x</a>', '<a>x</a>'],
    'an image with an error handler' => ['Text <img src=x onerror=alert(1)> more', 'Text  more'],
    'a frame' => ['<iframe src="https://evil.example"></iframe>ok', 'ok'],
    'an svg with a handler' => ['<svg onload=alert(1)><circle/></svg>ok', 'ok'],
    'a style sheet' => ['<style>body{display:none}</style>ok', 'ok'],
    'an unknown element keeps its text' => ['<marquee>moving <i>text</i></marquee>', 'moving <i>text</i>'],
    'a script inside an allowed element' => ['<p>a<script>alert(1)</script>b</p>', '<p>ab</p>'],
    'a comment' => ['a<!-- <script>alert(1)</script> -->b', 'ab'],
    'a form' => ['<form action="https://evil.example"><input name="password"></form>ok', 'ok'],
]);

test('the text alone drops the markup and what a script contains', function () {
    expect(Html::text('<i>Rollrasen</i> &amp; <b>more</b><script>alert(1)</script>'))->toBe('Rollrasen & more');
});
