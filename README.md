# PHP Framework

Un framework PHP moderne inspiré de Symfony, construit from scratch.

[![CI](https://github.com/IMAMx39/Php-framework/actions/workflows/ci.yml/badge.svg)](https://github.com/IMAMx39/Php-framework/actions/workflows/ci.yml)
[![PHP](https://img.shields.io/badge/PHP-8.1%2B-blue)](https://php.net)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)

---

## Installation

```bash
composer create-project imamx39/php-framework mon-projet
cd mon-projet
```

Le script d'init s'exécute automatiquement — `.env` copié, répertoires créés.

Configure ton `.env` :

```env
APP_NAME=MonApp
DATABASE_URL=mysql://root:@127.0.0.1:3306/ma_base
```

Lance le serveur de développement :

```bash
composer serve
# → http://localhost:8000
```

---

## Démarrage rapide

### 1. Créer une entité

```bash
php bin/console make:entity Product
```

Génère `app/Entity/Product.php` et `app/Repository/ProductRepository.php`.

### 2. Créer une migration

```bash
php bin/console make:migration CreateProductsTable
```

Édite le fichier généré dans `migrations/` :

```php
public function up(): void
{
    $this->execute('
        CREATE TABLE products (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            name       VARCHAR(255) NOT NULL,
            price      DECIMAL(10,2) NOT NULL,
            created_at VARCHAR(255)
        )
    ');
}

public function down(): void
{
    $this->execute('DROP TABLE products');
}
```

### 3. Lancer les migrations

```bash
php bin/console migrate
php bin/console migrate:status    # voir l'état
php bin/console migrate:rollback  # annuler la dernière
```

### 4. Créer un contrôleur

```bash
php bin/console make:controller Product
```

Génère `app/Controller/ProductController.php` :

```php
#[Route('/product', name: 'product.index', methods: ['GET'])]
public function index(Request $request): Response
{
    $products = $this->container->get(ProductRepository::class)->findAll();

    return $this->render('product/index.html.twig', [
        'products' => $products,
    ]);
}
```

---

## Fonctionnalités

### Routing

```php
// config/routes.php
$router->get('/products',         [ProductController::class, 'index']);
$router->post('/products',        [ProductController::class, 'store']);
$router->get('/products/{id}',    [ProductController::class, 'show']);
$router->put('/products/{id}',    [ProductController::class, 'update']);
$router->delete('/products/{id}', [ProductController::class, 'destroy']);
```

Via attributs PHP 8 :

```php
#[Route('/products/{id}', name: 'product.show', methods: ['GET'])]
public function show(Request $request, int $id): Response { ... }
```

---

### ORM

```php
// Lecture
$product  = $repo->find(1);
$products = $repo->findAll();
$actifs   = $repo->findBy(['active' => 1], ['name' => 'ASC']);
$one      = $repo->findOneBy(['email' => 'a@b.com']);
$total    = $repo->count(['active' => 1]);

// Persistance
$repo->save($product);
$repo->delete($product);

// Pagination
$page = $repo->paginate(page: 1, perPage: 15);
$page->items();     // entités de la page
$page->total();     // nombre total
$page->lastPage();  // dernière page
$page->hasMore();   // page suivante ?
$page->from();      // rang du 1er élément
$page->to();        // rang du dernier
```

#### Relations

```php
#[Entity(table: 'posts', repositoryClass: PostRepository::class)]
class Post
{
    #[ManyToOne(targetEntity: User::class, joinColumn: 'user_id')]
    private ?User $author = null;

    #[OneToMany(targetEntity: Comment::class, mappedBy: 'post_id')]
    private array $comments = [];

    #[ManyToMany(
        targetEntity: Tag::class,
        joinTable: 'post_tags',
        joinColumn: 'post_id',
        inverseJoinColumn: 'tag_id',
    )]
    private array $tags = [];
}

// Chargement explicite des relations
$post = $repo->find(1, relations: ['author', 'tags', 'comments']);

// Gestion ManyToMany
$repo->attach($post, $tag, 'tags');
$repo->detach($post, $tag, 'tags');
$repo->sync($post, [$tag1, $tag2], 'tags');
```

---

### Authentication

```php
$auth = $container->get(Auth::class);

if ($auth->attempt($email, $password)) {
    return Response::redirect('/dashboard');
}

$auth->check();   // bool — connecté ?
$auth->user();    // User|null
$auth->id();      // int|null
$auth->logout();
```

#### Middlewares

```php
new AuthMiddleware($auth)                                         // redirige → /login
new GuestMiddleware($auth)                                        // redirige → /dashboard
new CsrfMiddleware($csrfManager)                                  // vérifie le token CSRF
new ThrottleMiddleware($limiter, maxAttempts: 5, decaySeconds: 60) // rate limiting
```

---

### Formulaires

```php
class LoginFormType extends AbstractFormType
{
    public function buildForm(FormBuilder $builder): void
    {
        $builder
            ->add('email',    'email',    ['rules' => 'required|email'])
            ->add('password', 'password', ['rules' => 'required|min:8']);
    }
}

// Dans le contrôleur
$form = $factory->create(new LoginFormType());
$form->handleRequest($request);

if ($form->isSubmitted() && $form->isValid()) {
    $data = $form->getData();
}
```

Dans Twig :

```twig
{{ form_start(form, '/login', 'POST') }}
    {{ form_row(form, 'email') }}
    {{ form_row(form, 'password') }}
    {{ csrf_field() }}
    <button type="submit">Connexion</button>
{{ form_end() }}
```

Types de champs disponibles : `text`, `email`, `password`, `number`, `textarea`, `select`, `checkbox`, `hidden`.

---

### Validation

```php
$data = Validator::make($request->all(), [
    'name'     => 'required|string|min:2|max:100',
    'email'    => 'required|email',
    'age'      => 'required|integer|min:18',
    'role'     => 'required|in:admin,user',
    'password' => 'required|min:8|confirmed',
]);
```

Règles disponibles : `required`, `string`, `integer`, `numeric`, `boolean`, `email`, `url`, `min`, `max`, `between`, `in`, `not_in`, `confirmed`, `regex`.

---

### Cache

```php
$cache = new FileCache(dirname(__DIR__) . '/var/cache');

$cache->put('key', $value, ttl: 3600);  // TTL en secondes
$cache->get('key', default: null);
$cache->has('key');
$cache->forget('key');
$cache->flush();

// Mémoïsation — exécute le callback uniquement si absent du cache
$products = $cache->remember('products.all', 600, fn() => $repo->findAll());
```

---

### Mailer

```php
$message = (new Message())
    ->from('noreply@monapp.com', 'Mon App')
    ->to($user->getEmail(), $user->getName())
    ->subject('Bienvenue !')
    ->html('<h1>Bonjour ' . $user->getName() . ' !</h1>')
    ->text('Bonjour ' . $user->getName() . ' !');

$mailer->send($message);
```

Configuration SMTP dans `.env` :

```env
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=xxxxx
MAIL_PASSWORD=xxxxx
MAIL_ENCRYPTION=tls
```

En développement, utilise `NullMailer` — il absorbe les envois sans les envoyer.

---

### Serializer

```php
$serializer = new Serializer();

// Objet → tableau / JSON
$array = $serializer->normalize($product);
$json  = $serializer->toJson($product);
$json  = $serializer->toJson($products);          // collection

// Groupes — masquer des champs selon le contexte
$public = $serializer->normalize($user, groups: ['public']);
```

Annoter les propriétés par groupe :

```php
#[Column(type: 'string')]
#[SerializeGroup('admin')]          // visible uniquement pour le groupe 'admin'
private string $passwordHash;

#[Column(type: 'string')]
#[SerializeGroup('public', 'admin')] // visible pour 'public' ET 'admin'
private string $name;
```

Dans un contrôleur API :

```php
return new JsonResponse($serializer->normalize($product));
```

---

### File Storage

```php
$storage = new LocalStorage(dirname(__DIR__) . '/storage/app', '/storage');

$storage->put('avatars/user-1.jpg', $imageContent);
$content = $storage->get('avatars/user-1.jpg');
$url     = $storage->url('avatars/user-1.jpg');  // → /storage/avatars/user-1.jpg
$storage->exists('avatars/user-1.jpg');
$storage->delete('avatars/user-1.jpg');

// Upload depuis un formulaire HTML
$path = $storage->putUpload($_FILES['avatar'], directory: 'avatars');

// Lister les fichiers
$files = $storage->files('avatars');
$all   = $storage->files('', recursive: true);
```

---

### Rate Limiter

```php
$limiter = new RateLimiter($cache);

// Vérification manuelle
if (!$limiter->attempt("login:{$ip}", maxAttempts: 5, decaySeconds: 60)) {
    return new Response('Trop de tentatives. Réessaie dans 1 minute.', 429);
}

$limiter->remaining('login:' . $ip, 5); // tentatives restantes
$limiter->clear('login:' . $ip);        // remettre à zéro

// Via middleware (appliqué globalement ou par route)
new ThrottleMiddleware($limiter, maxAttempts: 60, decaySeconds: 60)
```

---

### EventDispatcher

```php
$dispatcher = $container->get(EventDispatcher::class);

// S'abonner à un événement kernel
$dispatcher->on(KernelEvents::REQUEST, function (RequestEvent $event) {
    // court-circuiter avec une réponse directe
    $event->setResponse(new Response('Maintenance', 503));
});

// Événements personnalisés
$dispatcher->on('user.registered', function ($event) {
    // envoyer un email de bienvenue
}, priority: 10);

$dispatcher->emit('user.registered', new Event());
```

---

### Logger (PSR-3)

```php
$logger = $container->get(Logger::class);

$logger->info('Utilisateur connecté', ['user_id' => 42]);
$logger->warning('Tentative échouée', ['ip' => $ip]);
$logger->error('Erreur critique', ['exception' => $e]);
```

Niveaux disponibles (RFC 5424) : `emergency` `alert` `critical` `error` `warning` `notice` `info` `debug`.

Logs écrits dans `var/logs/app.log` et `var/logs/error.log`.

---

### CSRF Protection

```php
// Middleware global
$pipeline->pipe(new CsrfMiddleware($csrfManager));

// Avec exemptions (webhooks, API)
new CsrfMiddleware($csrfManager, exemptPaths: ['/api/', '/webhook/'])
```

Dans Twig :

```twig
<form method="POST">
    {{ csrf_field() }}
</form>
```

Pour les requêtes AJAX :

```js
fetch('/api/data', {
    method: 'POST',
    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
});
```

---

## Console

```bash
# Génération de code
php bin/console make:entity     Product
php bin/console make:migration  CreateProductsTable
php bin/console make:controller Product

# Migrations
php bin/console migrate
php bin/console migrate:status
php bin/console migrate:rollback
```

---

## Structure du projet

```
mon-projet/
├── app/
│   ├── Controller/        # Contrôleurs de l'application
│   ├── Entity/            # Entités ORM
│   └── Repository/        # Repositories
├── bin/
│   ├── console            # CLI du framework
│   └── setup              # Script d'initialisation
├── config/
│   ├── routes.php         # Définition des routes
│   └── services.php       # Conteneur de services
├── migrations/            # Fichiers de migration versionnés
├── public/
│   └── index.php          # Point d'entrée HTTP
├── src/                   # Code source du framework
├── templates/             # Templates Twig
├── tests/                 # Tests PHPUnit
├── var/
│   ├── cache/             # Cache (auto-généré)
│   └── logs/              # Logs (auto-généré)
├── storage/
│   └── app/               # Fichiers uploadés
├── .env                   # Configuration locale (ignoré par git)
├── .env.example           # Template de configuration
└── composer.json
```

---

### Queue / Jobs

Traitement de tâches en arrière-plan — emails, exports, notifications.

```php
// 1. Créer un job
class SendWelcomeEmailJob implements JobInterface
{
    public function __construct(
        private readonly string $email,
        private readonly string $name,
    ) {}

    // Les dépendances de handle() sont injectées automatiquement
    public function handle(MailerInterface $mailer): void
    {
        $message = (new Message())
            ->to($this->email, $this->name)
            ->subject('Bienvenue !')
            ->html("<h1>Bonjour {$this->name} !</h1>");

        $mailer->send($message);
    }
}

// 2. Dispatcher le job (dans un contrôleur)
public function register(CreateUserDTO $dto): Response
{
    // ... créer l'utilisateur ...

    $this->dispatcher->dispatch(new SendWelcomeEmailJob($dto->email, $dto->name));

    // Avec délai (60 secondes)
    $this->dispatcher->dispatch(new ReminderJob($user->id), delay: 60);

    return $this->json(['status' => 'created'], 201);
}

// 3. Lancer le worker
```

```bash
php bin/console queue:work          # boucle infinie
php bin/console queue:work --once   # traite un seul job
php bin/console queue:flush         # vide la queue
```

**Configuration `.env` :**

```env
QUEUE_DRIVER=file    # "file" (défaut) ou "sync" (test/dev — exécution immédiate)
```

**Drivers disponibles :**

| Driver | Usage | Stockage |
|---|---|---|
| `file` | Production | `var/queue/*.json` — un fichier par job |
| `sync` | Test / Dev | Exécution immédiate, pas de stockage |

**Retry automatique :** 3 tentatives par défaut. En cas d'échec définitif, le job est déplacé dans `var/queue/failed/`.

---

## Tests

```bash
composer test
# ou
vendor/bin/phpunit --testdox
```

**445 tests · 720 assertions** — tout vert sur PHP 8.1 / 8.2 / 8.3 / 8.4.

---

## Licence

MIT — [IMAMx39](https://github.com/IMAMx39)
