<?php

declare(strict_types=1);

namespace App\Tests\Functional\Internationalization;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class TranslationApiTest extends WebTestCase
{
    public function testGetTranslationsWithDefaultDomainAndLocale(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/translations');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');

        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('member', $data);
        $this->assertCount(1, $data['member']);
        $this->assertSame('messages', $data['member'][0]['domain']);
        $this->assertSame('en', $data['member'][0]['locale']);
        $this->assertArrayHasKey('translations', $data['member'][0]);
    }

    public function testGetTranslationsWithMessagesEnglish(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/translations', ['domain' => 'messages', 'locale' => 'en']);

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertSame('messages', $data['member'][0]['domain']);
        $this->assertSame('en', $data['member'][0]['locale']);

        $translations = $data['member'][0]['translations'];
        $this->assertArrayHasKey('common.save', $translations);
        $this->assertSame('Save', $translations['common.save']);
        $this->assertArrayHasKey('buttons.add_to_cart', $translations);
        $this->assertSame('Add to Cart', $translations['buttons.add_to_cart']);
    }

    public function testGetTranslationsWithMessagesFrench(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/translations', ['domain' => 'messages', 'locale' => 'fr']);

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertSame('messages', $data['member'][0]['domain']);
        $this->assertSame('fr', $data['member'][0]['locale']);

        $translations = $data['member'][0]['translations'];
        $this->assertArrayHasKey('common.save', $translations);
        $this->assertSame('Enregistrer', $translations['common.save']);
        $this->assertArrayHasKey('buttons.add_to_cart', $translations);
        $this->assertSame('Ajouter au Panier', $translations['buttons.add_to_cart']);
    }

    public function testGetTranslationsWithMessagesGerman(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/translations', ['domain' => 'messages', 'locale' => 'de']);

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertSame('messages', $data['member'][0]['domain']);
        $this->assertSame('de', $data['member'][0]['locale']);

        $translations = $data['member'][0]['translations'];
        $this->assertArrayHasKey('common.save', $translations);
        $this->assertSame('Speichern', $translations['common.save']);
        $this->assertArrayHasKey('buttons.add_to_cart', $translations);
        $this->assertSame('In den Warenkorb', $translations['buttons.add_to_cart']);
    }

    public function testGetTranslationsWithValidatorsDomain(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/translations', ['domain' => 'validators', 'locale' => 'en']);

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertSame('validators', $data['member'][0]['domain']);
        $translations = $data['member'][0]['translations'];
        $this->assertArrayHasKey('required', $translations);
        $this->assertArrayHasKey('email.invalid', $translations);
    }

    public function testGetTranslationsWithValidatorsFrench(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/translations', ['domain' => 'validators', 'locale' => 'fr']);

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);

        $translations = $data['member'][0]['translations'];
        $this->assertArrayHasKey('required', $translations);
        $this->assertSame('Ce champ est obligatoire', $translations['required']);
    }

    public function testGetTranslationsWithValidatorsGerman(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/translations', ['domain' => 'validators', 'locale' => 'de']);

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);

        $translations = $data['member'][0]['translations'];
        $this->assertArrayHasKey('required', $translations);
        $this->assertSame('Dieses Feld ist erforderlich', $translations['required']);
    }

    public function testGetTranslationsWithEmailsDomain(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/translations', ['domain' => 'emails', 'locale' => 'en']);

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertSame('emails', $data['member'][0]['domain']);
        $translations = $data['member'][0]['translations'];
        $this->assertArrayHasKey('customer.welcome.subject', $translations);
        $this->assertArrayHasKey('order.confirmation.subject', $translations);
    }

    public function testGetTranslationsReturnsClientErrorForInvalidDomain(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/translations', ['domain' => 'invalid_domain', 'locale' => 'en']);

        $this->assertResponseStatusCodeSame(500);

        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertStringContainsString('Invalid domain', $data['detail']);
    }

    public function testGetTranslationsReturnsClientErrorForInvalidLocale(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/translations', ['domain' => 'messages', 'locale' => 'es']);

        $this->assertResponseStatusCodeSame(500);

        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertStringContainsString('Language code', $data['detail']);
    }

    public function testGetTranslationsIsPubliclyAccessible(): void
    {
        $client = static::createClient();

        // Request without authentication
        $client->request('GET', '/api/translations', ['domain' => 'messages', 'locale' => 'fr']);

        // Should succeed (not 401 Unauthorized)
        $this->assertResponseIsSuccessful();
    }

    public function testGetTranslationsReturnsCorrectContentType(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/translations', ['domain' => 'messages', 'locale' => 'en']);

        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
    }

    public function testGetTranslationsIncludesJsonLdContext(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/translations', ['domain' => 'messages', 'locale' => 'en']);

        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('@context', $data);
        $this->assertArrayHasKey('@type', $data);
        $this->assertSame('Collection', $data['@type']);
    }

    public function testGetTranslationsPaginationIsDisabled(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/translations', ['domain' => 'messages', 'locale' => 'en']);

        $data = json_decode($client->getResponse()->getContent(), true);

        // Should return all translations, not paginated
        $this->assertCount(1, $data['member']); // One translation resource
        $this->assertGreaterThan(10, count($data['member'][0]['translations'])); // Many translations
    }

    public function testGetTranslationsHandlesMissingParameters(): void
    {
        $client = static::createClient();

        // Request with no parameters should use defaults
        $client->request('GET', '/api/translations');

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);

        // Should default to messages and en
        $this->assertSame('messages', $data['member'][0]['domain']);
        $this->assertSame('en', $data['member'][0]['locale']);
    }

    #[DataProvider('translationDataProvider')]
    public function testGetTranslationsReturnsExpectedKeys(string $domain, string $locale, array $expectedKeys): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/translations', ['domain' => $domain, 'locale' => $locale]);

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        $translations = $data['member'][0]['translations'];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $translations, "Missing translation key: {$key}");
        }
    }

    public static function translationDataProvider(): array
    {
        return [
            'Messages EN' => [
                'messages',
                'en',
                ['common.save', 'common.cancel', 'buttons.add_to_cart', 'navigation.home'],
            ],
            'Messages FR' => [
                'messages',
                'fr',
                ['common.save', 'common.cancel', 'buttons.add_to_cart', 'navigation.home'],
            ],
            'Messages DE' => [
                'messages',
                'de',
                ['common.save', 'common.cancel', 'buttons.add_to_cart', 'navigation.home'],
            ],
            'Validators EN' => [
                'validators',
                'en',
                ['required', 'email.invalid', 'too_short'],
            ],
            'Validators FR' => [
                'validators',
                'fr',
                ['required', 'email.invalid', 'too_short'],
            ],
            'Validators DE' => [
                'validators',
                'de',
                ['required', 'email.invalid', 'too_short'],
            ],
            'Emails EN' => [
                'emails',
                'en',
                ['customer.welcome.subject', 'order.confirmation.subject'],
            ],
        ];
    }

    public function testGetTranslationsWithCaseVariations(): void
    {
        $client = static::createClient();

        // Test uppercase locale
        $client->request('GET', '/api/translations', ['domain' => 'messages', 'locale' => 'FR']);

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('fr', $data['member'][0]['locale']); // Should normalize to lowercase
    }
}
