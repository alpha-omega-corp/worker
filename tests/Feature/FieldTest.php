<?php

use App\Support\UiKit;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Illuminate\View\ViewException;

// Every site has the kit's field once anything of the kit is imported, so any
// page can write @field.
beforeEach(function () {
    $this->site = aSiteOfItsOwn();
    (new UiKit(new Filesystem))->import(['button']);
});

afterEach(fn () => File::deleteDirectory($this->site));

test('@field draws the three kinds through the kit\'s field', function () {
    $html = Blade::render(<<<'BLADE'
        <form>
            @field('email', label: 'Courriel', type: 'email', autocomplete: 'email', required: true)
            @field('message', label: 'Message', type: 'long', rows: 3, maxlength: 2000, class: 'sm:col-span-2')
            @field('service', label: 'Prestation', options: ['Coupe', 'Couleur'], choose: 'Choisir')
        </form>
        BLADE);

    expect($html)->toContain('<label for="field-email"')
        ->toMatch('/<input type="email"[^>]*name="email"[^>]*data-field="line"/')
        ->toMatch('/<input[^>]*required="required" autocomplete="email"/')
        ->toMatch('/<textarea rows="3"[^>]*data-field="long" maxlength="2000"/')
        ->toContain('class="space-y-1.5 sm:col-span-2"')
        ->toMatch('/<select[^>]*name="service"[^>]*data-field="choice"/')
        ->toContain('<option value="">Choisir</option>');
});

test('@field shares a hint with another field, and takes the focus when told', function () {
    expect(Blade::render("@field('phone', label: 'Téléphone', type: 'tel', describedby: 'contact-hint', autofocus: true)"))
        ->toContain('aria-describedby="contact-hint"')->toContain('autofocus="autofocus"');
});

test('@field takes only the arguments it names, and the kit refuses a field it would draw half right', function (string $template, string $refusal) {
    expect(fn () => Blade::render($template))->toThrow(ViewException::class, $refusal);
})->with([
    'a misspelt argument' => ["@field('email', lable: 'Courriel')", 'Unknown named parameter $lable'],
    'no label' => ["@field('email', label: '')", 'needs a name and a label'],
    'a type outside the kinds' => ["@field('cv', label: 'CV', type: 'file')", 'none of the three kinds'],
]);

test('every import brings the field, and a site\'s own copy is kept', function () {
    File::put(resource_path('views/components/kit/field.blade.php'), 'the site\'s own field');

    $result = (new UiKit(new Filesystem))->import(['badge']);

    expect(file_get_contents(resource_path('views/components/kit/field.blade.php')))->toBe('the site\'s own field')
        ->and($result['skipped'])->toBe([]);
});
