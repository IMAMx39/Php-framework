# PHP Framework

Un framework PHP moderne inspiré de Symfony et Laravel, construit from scratch.

[![CI](https://github.com/IMAMx39/Php-framework/actions/workflows/ci.yml/badge.svg)](https://github.com/IMAMx39/Php-framework/actions/workflows/ci.yml)
[![PHP](https://img.shields.io/badge/PHP-8.1%2B-blue)](https://php.net)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)

---

## Sommaire

- [Installation](#installation)
- [Configuration](#configuration)
- [Structure du projet](#structure-du-projet)
- [Démarrage rapide](#démarrage-rapide)
- [Fonctionnalités](#fonctionnalités)
  - [Routing](#routing)
  - [Contrôleurs & Injection](#contrôleurs--injection)
  - [ORM](#orm)
  - [DTO / Value Objects](#dto--value-objects)
  - [Collection](#collection)
  - [Query Scopes](#query-scopes)
  - [Gates & Policies](#gates--policies)
  - [Pipeline](#pipeline)
  - [Authentication](#authentication)
  - [Formulaires](#formulaires)
  - [Validation](#validation)
  - [Cache](#cache)
  - [Queue / Jobs](#queue--jobs)
  - [HTTP Client](#http-client)
  - [Mailer](#mailer)
  - [Serializer](#serializer)
  - [File Storage](#file-storage)
  - [Rate Limiter](#rate-limiter)
  - [Events](#events)
  - [Logger](#logger)
  - [CSRF Protection](#csrf-protection)
  - [Debug Toolbar](#debug-toolbar)
- [Console](#console)
- [Tests](#tests)

---

## Installation

```bash
composer create-project imamx39/php-framework mon-projet
cd mon-projet
```

Le script d'init s'exécute automatiquement : `.env` copié depuis `.env.example`, répertoires `var/` et `storage/` créés.

---

## Configuration

Édite `.env` à la racine du projet :

```env
APP_NAME=MonApp
APP_ENV=development
APP_DEBUG=true
APP_URL=http://localhost:8000

# Base de données
DATABASE_URL=mysql://root:@127.0.0.1:3306/ma_base
# SQLite (dev) :
# DATABASE_URL=sqlite:////chemin/absolu/vers/db.sqlite

# Mailer (optionnel — NullMailer si vide)
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=xxxxx
MAIL_PASSWORD=xxxxx
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@monapp.com
MAIL_FROM_NAME="${APP_NAME}"

# Queue (optionnel — "file" par défaut)
QUEUE_DRIVER=file   # ou "sync" pour le dev/test
```

Lance le serveur de développement :

```bash
composer serve
# → http://localhost:8000
```

---

## Structure du projet

```
mon-projet/
├── app/
│   ├── Controller/        # Contrôleurs de l'application
│   ├── Entity/            # Entités ORM
│   ├── Pipeline/          # Étapes de pipeline métier
│   └── Repository/        # Repositories
├── bin/
│   ├── console            # CLI
│   └── setup              # Script d'initialisation
├── config/
│   ├── routes.php         # Définition des routes
│   └── services.php       # Conteneur de services (DI)
├── migrations/            # Migrations SQL versionnées
├── public/
│   └── index.php          # Point d'entrée HTTP
├── src/                   # Code source du framework
├── templates/             # Templates Twig
├── tests/                 # Tests PHPUnit
├── var/
│   ├── cache/             # Cache compilé (auto)
│   ├── logs/              # Logs (auto)
│   └── queue/             # Jobs en attente (auto)
├── storage/app/           # Fichiers uploadés
├── .env                   # Config locale (ignoré par git)
├── .env.example           # Template à copier
└── composer.json
```

---

## Démarrage rapide

### 1. Créer une entité

```bash
php bin/console make:entity Product
```

Génère `app/Entity/Product.php` et `app/Repository/ProductRepository.php`.

```php
#[Entity(table: 'products', repositoryClass: ProductRepository::class)]
class Product
{
    #[Id] #[GeneratedValue] #[Column(type: 'integer')]
    private ?int $id = null;

    #[Column(type: 'string', length: 255)]
    private string $name;

    #[Column(type: 'decimal')]
    private float $price;
}
```

### 2. Créer et lancer une migration

```bash
php bin/console make:migration CreateProductsTable
```

Édite le fichier généré dans `migrations/` :

```php
public function up(): void
{
    $this->execute('
        CREATE TABLE products (
            id    INTEGER PRIMARY KEY AUTOINCREMENT,
            name  VARCHAR(255) NOT NULL,
            price DECIMAL(10,2) NOT NULL
        )
    ');
}

public function down(): void
{
    $this->execute('DROP TABLE products');
}
```

```bash
php bin/console migrate
php bin/console migrate:status    # état des migrations
php bin/console migrate:rollback  # annuler la dernière
```

### 3. Créer un contrôleur

```bash
php bin/console make:controller Product
```

```php
class ProductController extends AbstractController
{
    public function __construct(
        private readonly ProductRepository $repo,
    ) {}

    #[Route('/products', name: 'product.index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('product/index.html.twig', [
            'products' => $this->repo->findAll(),
        ]);
    }
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

Via attribut PHP 8 directement sur la méthode :

```php
#[Route('/products/{id}', name: 'product.show', methods: ['GET'])]
public function show(Request $request, int $id): Response
{
    $product = $this->repo->find($id);
    // ...
}
```

---

### Contrôleurs & Injection

Les dépendances sont injectées automatiquement dans le constructeur via le conteneur :

```php
class OrderController extends AbstractController
{
    public function __construct(
        private readonly OrderRepository  $orders,
        private readonly Dispatcher       $queue,
        private readonly Gate             $gate,
        private readonly Logger           $logger,
    ) {}
}
```

Helpers disponibles dans tout contrôleur :

```php
$this->render('template.html.twig', $data);  // réponse HTML Twig
$this->json($data, 200);                     // réponse JSON
$this->redirect('/url');                     // redirection
```

---

### ORM

#### Lecture

```php
$product  = $repo->find(1);
$products = $repo->findAll();
$actifs   = $repo->findBy(['active' => 1], ['name' => 'ASC']);
$one      = $repo->findOneBy(['email' => 'a@b.com']);
$total    = $repo->count(['active' => 1]);

// Pagination
$page = $repo->paginate(page: 1, perPage: 15);
$page->items();     // entités de la page courante
$page->total();     // nombre total d'enregistrements
$page->lastPage();
$page->hasMore();
$page->from();      // rang du 1er élément
$page->to();        // rang du dernier
```

#### Persistance

```php
$repo->save($product);   // INSERT si id null, UPDATE sinon
$repo->delete($product);
```

#### Relations

```php
#[Entity(table: 'posts')]
class Post
{
    #[ManyToOne(targetEntity: User::class, joinColumn: 'user_id')]
    private ?User $author = null;

    #[OneToMany(targetEntity: Comment::class, mappedBy: 'post_id')]
    private array $comments = [];

    #[ManyToMany(
        targetEntity: Tag::class,
        joinTable:    'post_tags',
        joinColumn:   'post_id',
        inverseJoinColumn: 'tag_id',
    )]
    private array $tags = [];
}

// Chargement explicite
$post = $repo->find(1, relations: ['author', 'tags', 'comments']);

// Gestion ManyToMany
$repo->attach($post, $tag, 'tags');
$repo->detach($post, $tag, 'tags');
$repo->sync($post, [$tag1, $tag2], 'tags');
```

#### Enum Support

Les `BackedEnum` PHP sont castés automatiquement :

```php
enum Status: string
{
    case Active   = 'active';
    case Inactive = 'inactive';
}

#[Entity(table: 'users')]
class User
{
    #[Column(type: 'string')]
    private Status $status = Status::Active;
}

// Stocké en DB : 'active' | En PHP : Status::Active
$user->getStatus() === Status::Active; // true
```

---

### DTO / Value Objects

Objets immutables créés et validés automatiquement depuis la `Request` :

```php
class CreateUserDTO extends AbstractDTO
{
    public function __construct(
        #[Validate('required|email')]
        public readonly string $email,

        #[Validate('required|min:8')]
        public readonly string $password,

        #[Validate('required|min:2|max:100')]
        public readonly string $name,

        public readonly string $role = 'user',
    ) {}
}
```

Dans le contrôleur, le DTO est injecté directement — zéro boilerplate :

```php
public function register(CreateUserDTO $dto): Response
{
    // $dto est déjà validé, castés et prêt à l'emploi
    $user = new User($dto->email, $dto->name, $dto->role);
    $this->repo->save($user);

    return $this->json(['status' => 'created'], 201);
}
```

Si la validation échoue, une `ValidationException` est levée automatiquement avec les erreurs.

**Casts automatiques** : `int`, `float`, `bool`, `string`, `DateTimeImmutable`, `DateTime`.

---

### Collection

Wrapper fluent et chaînable autour d'un tableau, inspiré de Laravel :

```php
$users = collect($repo->findAll());

// Filtrer, transformer, trier
$emails = $users
    ->filter(fn($u) => $u->isActive())
    ->sortBy(fn($u) => $u->getName())
    ->map(fn($u) => $u->getEmail())
    ->values();

// Agrégats
$total    = collect($orders)->sum(fn($o) => $o->getTotal());
$average  = collect($scores)->avg();
$max      = collect($prices)->max();

// Grouper / découper
$byRole   = collect($users)->groupBy(fn($u) => $u->getRole());
$chunks   = collect($items)->chunk(10);

// Recherche
$admin    = collect($users)->first(fn($u) => $u->getRole() === 'admin');
$hasAdmin = collect($users)->some(fn($u) => $u->getRole() === 'admin');
$allOk    = collect($items)->every(fn($i) => $i->isValid());

// Transformation
$flat     = collect([[1, 2], [3, 4]])->flatten();
$names    = collect($users)->pluck('name');
$unique   = collect([1, 2, 2, 3])->unique()->values();

// Sérialisation
$array = $collection->toArray();
$json  = $collection->toJson();
```

Le helper global `collect()` est disponible partout dans l'application.

---

### Query Scopes

Encapsuler des filtres réutilisables directement dans le repository :

```php
class UserRepository extends AbstractRepository
{
    protected function getEntityClass(): string { return User::class; }

    // Définir des scopes — préfixe "scope" + nom en PascalCase
    public function scopeActive(QueryBuilder $qb): QueryBuilder
    {
        return $qb->where('active', 1);
    }

    public function scopeRole(QueryBuilder $qb, string $role): QueryBuilder
    {
        return $qb->where('role', $role);
    }

    public function scopeRecent(QueryBuilder $qb, int $days = 30): QueryBuilder
    {
        return $qb->where('created_at', '>=', date('Y-m-d', strtotime("-{$days} days")));
    }
}
```

Utilisation avec chaînage fluent :

```php
// Récupérer
$users = $repo->active()->get();                       // Collection
$admin = $repo->active()->role('admin')->first();      // ?object
$count = $repo->active()->role('user')->count();       // int

// Paginer
$page  = $repo->active()->recent(7)->paginate(page: 1, perPage: 20);

// Avec chargement de relations
$users = $repo->active()->get(relations: ['profile']);
```

---

### Gates & Policies

Système d'autorisation en deux niveaux : **gates** simples et **policies** par entité.

#### Définir des abilities

```php
// config/services.php (Gate déjà enregistré en singleton)
$gate = $container->get(Gate::class);

$gate->define('admin',     fn(User $u) => $u->getRole() === 'admin');
$gate->define('moderator', fn(User $u) => in_array($u->getRole(), ['admin', 'moderator']));
```

#### Policies par entité

```php
class PostPolicy
{
    public function edit(User $user, Post $post): bool
    {
        return $user->getId() === $post->getUserId();
    }

    public function delete(User $user, Post $post): bool
    {
        return $user->getRole() === 'admin'
            || $user->getId() === $post->getUserId();
    }
}

// Enregistrement
$gate->policy(Post::class, PostPolicy::class);
```

#### Vérifications dans les contrôleurs

```php
// Vérifier
$gate->allows('admin');                // bool
$gate->allows('edit', $post);         // bool — passe par la policy
$gate->denies('edit', $post);         // bool

// Autoriser (lève ForbiddenException HTTP 403 si refusé)
$gate->authorize('edit', $post);

// Vérifier le rôle
$gate->hasRole('admin');
$gate->hasRole('admin', 'moderator'); // OR
```

---

### Pipeline

Faire transiter une valeur à travers une série d'étapes ordonnées et indépendantes.

```php
// Chaque étape reçoit la valeur et un callable $next
class ValidateStock
{
    public function handle(OrderData $order, callable $next): OrderData
    {
        if ($order->quantity > $this->available()) {
            throw new HttpException(422, 'Stock insuffisant.');
            // ← court-circuit : les étapes suivantes ne s'exécutent pas
        }
        return $next($order); // ← passe à l'étape suivante
    }
}

class ApplyDiscount
{
    public function handle(OrderData $order, callable $next): OrderData
    {
        if ($order->total >= 100) {
            $order->total *= 0.90; // -10 %
        }
        return $next($order);
    }
}
```

```php
// Dans un contrôleur ou service
$order = Pipeline::send(new OrderData(...))
    ->through([
        ValidateStock::class,
        ApplyDiscount::class,
        SendInvoice::class,
    ])
    ->thenReturn();
```

Fonctionnalités avancées :

```php
// Closures inline
Pipeline::send($value)
    ->through([
        fn($v, $next) => $next(trim($v)),
        fn($v, $next) => $next(strtolower($v)),
    ])
    ->thenReturn();

// Ajouter une étape dynamiquement
$pipeline->pipe(fn($v, $next) => $next($v));

// Méthode personnalisée (défaut : handle)
->via('process')

// Destination finale
->then(fn($order) => new JsonResponse($order));

// Immutable — through() et pipe() retournent un clone
$base     = Pipeline::send($v)->through([StepA::class]);
$extended = $base->pipe(StepB::class); // $base inchangé
```

---

### Authentication

```php
$auth = $container->get(Auth::class);

// Connexion
if ($auth->attempt($email, $password)) {
    return $this->redirect('/dashboard');
}

// Vérifications
$auth->check();   // bool
$auth->user();    // User|null
$auth->id();      // int|null
$auth->logout();
```

#### Middlewares Auth

```php
// config/routes.php ou Application
new AuthMiddleware($auth)       // redirige vers /login si non connecté
new GuestMiddleware($auth)      // redirige vers /dashboard si déjà connecté
```

---

### Formulaires

```php
class RegisterFormType extends AbstractFormType
{
    public function buildForm(FormBuilder $builder): void
    {
        $builder
            ->add('name',     'text',     ['rules' => 'required|min:2'])
            ->add('email',    'email',    ['rules' => 'required|email'])
            ->add('password', 'password', ['rules' => 'required|min:8|confirmed'])
            ->add('password_confirmation', 'password', []);
    }
}

// Contrôleur
$form = $factory->create(new RegisterFormType());
$form->handleRequest($request);

if ($form->isSubmitted() && $form->isValid()) {
    $data = $form->getData(); // tableau des valeurs validées
}
```

Dans Twig :

```twig
{{ form_start(form, '/register', 'POST') }}
    {{ form_row(form, 'name') }}
    {{ form_row(form, 'email') }}
    {{ form_row(form, 'password') }}
    {{ form_row(form, 'password_confirmation') }}
    {{ csrf_field() }}
    <button type="submit">S'inscrire</button>
{{ form_end() }}
```

Types disponibles : `text` `email` `password` `number` `textarea` `select` `checkbox` `hidden`.

---

### Validation

```php
$validator = new Validator();
$errors    = $validator->validate($request->all(), [
    'name'     => 'required|string|min:2|max:100',
    'email'    => 'required|email',
    'age'      => 'required|integer|min:18',
    'role'     => 'required|in:admin,user,moderator',
    'password' => 'required|min:8|confirmed',
    'website'  => 'url',
]);

if (!empty($errors)) {
    // $errors = ['email' => ['Email invalide.'], ...]
}
```

Règles disponibles :

| Règle | Description |
|---|---|
| `required` | Champ obligatoire |
| `string` | Doit être une chaîne |
| `integer` | Doit être un entier |
| `numeric` | Entier ou flottant |
| `boolean` | true / false |
| `email` | Format email valide |
| `url` | URL valide |
| `min:N` | Longueur ou valeur minimale |
| `max:N` | Longueur ou valeur maximale |
| `between:N,M` | Entre N et M |
| `in:a,b,c` | Valeur dans la liste |
| `not_in:a,b` | Valeur absente de la liste |
| `confirmed` | Doit correspondre au champ `_confirmation` |
| `regex:/pattern/` | Correspond à l'expression régulière |

---

### Cache

```php
$cache = $container->get(CacheInterface::class);

$cache->put('key', $value, ttl: 3600);   // TTL en secondes
$cache->get('key', default: null);
$cache->has('key');
$cache->forget('key');
$cache->flush();

// Mémoïsation
$users = $cache->remember('users.all', 600, fn() => $repo->findAll());
```

Drivers disponibles : `FileCache` (production), `ArrayCache` (tests).

---

### Queue / Jobs

Traitement asynchrone de tâches lourdes : envois d'emails, exports, notifications.

```php
// 1. Définir un job
class SendWelcomeEmailJob implements JobInterface
{
    public function __construct(
        private readonly string $email,
        private readonly string $name,
    ) {}

    // Les dépendances de handle() sont injectées automatiquement
    public function handle(MailerInterface $mailer): void
    {
        $mailer->send(
            (new Message())
                ->to($this->email, $this->name)
                ->subject('Bienvenue !')
                ->html("<h1>Bonjour {$this->name} !</h1>")
        );
    }
}

// 2. Dispatcher depuis un contrôleur
public function register(CreateUserDTO $dto): Response
{
    $this->dispatcher->dispatch(new SendWelcomeEmailJob($dto->email, $dto->name));

    // Avec délai (en secondes)
    $this->dispatcher->dispatch(new ReminderJob($user->id), delay: 3600);

    return $this->json(['status' => 'created'], 201);
}
```

```bash
php bin/console queue:work          # worker en boucle infinie
php bin/console queue:work --once   # traite un seul job
php bin/console queue:flush         # vide la queue
```

| Driver | Usage | Comportement |
|---|---|---|
| `file` (défaut) | Production | Jobs sérialisés dans `var/queue/` |
| `sync` | Dev / Tests | Exécution immédiate, pas de fichier |

Retry automatique : 3 tentatives. Échec définitif → déplacé dans `var/queue/failed/`.

---

### HTTP Client

Client HTTP fluent basé sur cURL pour appeler des APIs externes :

```php
$client = new HttpClient(baseUrl: 'https://api.example.com');

// GET
$response = $client->get('/users');
$users    = $response->json();        // array décodé
$status   = $response->status();      // 200
$ok       = $response->ok();          // bool

// POST avec JSON
$response = $client->post('/users', [
    'name'  => 'Alice',
    'email' => 'alice@example.com',
]);

// Headers, auth, timeout
$client = (new HttpClient())
    ->withToken($apiKey)                          // Bearer token
    ->withBasicAuth($user, $password)             // Basic Auth
    ->withHeaders(['X-App-Version' => '1.0'])
    ->withTimeout(10);

// Méthodes disponibles
$client->get('/resource');
$client->post('/resource', $data);
$client->put('/resource/1', $data);
$client->patch('/resource/1', $data);
$client->delete('/resource/1');

// Gestion des erreurs
if ($response->failed()) {
    // clientError() → 4xx  |  serverError() → 5xx
}
$response->throw(); // lève HttpClientException si status >= 400
```

---

### Mailer

```php
$message = (new Message())
    ->from('noreply@monapp.com', 'Mon App')
    ->to($user->getEmail(), $user->getName())
    ->cc('admin@monapp.com')
    ->subject('Bienvenue !')
    ->html('<h1>Bonjour !</h1><p>Ton compte est créé.</p>')
    ->text('Bonjour ! Ton compte est créé.');

$mailer->send($message);
```

En développement, le `NullMailer` absorbe les envois sans les transmettre.

---

### Serializer

```php
$serializer = $container->get(Serializer::class);

// Objet → tableau / JSON
$array = $serializer->normalize($product);
$json  = $serializer->toJson($product);
$json  = $serializer->toJson($products);  // tableau d'objets

// Groupes — exposer différents champs selon le contexte
$public = $serializer->normalize($user, groups: ['public']);
$admin  = $serializer->normalize($user, groups: ['admin']);
```

Annoter les propriétés :

```php
#[SerializeGroup('public', 'admin')]
private string $name;

#[SerializeGroup('admin')]            // invisible pour le groupe 'public'
private string $passwordHash;
```

---

### File Storage

```php
$storage = $container->get(LocalStorage::class);

// Écriture / lecture
$storage->put('avatars/user-1.jpg', $imageContent);
$content = $storage->get('avatars/user-1.jpg');
$url     = $storage->url('avatars/user-1.jpg'); // → /storage/avatars/user-1.jpg

// Vérification / suppression
$storage->exists('avatars/user-1.jpg');
$storage->delete('avatars/user-1.jpg');

// Upload depuis un formulaire
$path = $storage->putUpload($_FILES['avatar'], directory: 'avatars');

// Lister
$files = $storage->files('avatars');
$all   = $storage->files('', recursive: true);
```

---

### Rate Limiter

```php
$limiter = $container->get(RateLimiter::class);

if (!$limiter->attempt("login:{$ip}", maxAttempts: 5, decaySeconds: 60)) {
    return new Response('Trop de tentatives. Réessaie dans 1 minute.', 429);
}

$limiter->remaining("login:{$ip}", 5); // tentatives restantes
$limiter->clear("login:{$ip}");        // remettre à zéro
```

Via middleware (appliqué sur une route ou globalement) :

```php
new ThrottleMiddleware($limiter, maxAttempts: 60, decaySeconds: 60)
```

---

### Events

```php
$dispatcher = $container->get(EventDispatcher::class);

// S'abonner
$dispatcher->on('user.registered', function (Event $event) {
    // envoyer email de bienvenue
}, priority: 10);

// Émettre
$dispatcher->emit('user.registered', new UserRegisteredEvent($user));

// Événements kernel (court-circuitage possible)
$dispatcher->on(KernelEvents::REQUEST, function (RequestEvent $event) {
    $event->setResponse(new Response('En maintenance.', 503));
});
```

---

### Logger

Compatible PSR-3, deux handlers configurés par défaut :

```php
$logger = $container->get(Logger::class);

$logger->debug('Requête reçue', ['url' => $request->getUri()]);
$logger->info('Utilisateur connecté', ['user_id' => $auth->id()]);
$logger->warning('Tentative suspecte', ['ip' => $ip]);
$logger->error('Erreur critique', ['exception' => $e->getMessage()]);
```

- `var/logs/app.log` — tous les niveaux (DEBUG+ en dev, INFO+ en prod)
- `var/logs/error.log` — ERROR et supérieur uniquement

---

### CSRF Protection

```php
// Middleware global (déjà configuré)
new CsrfMiddleware($csrfManager)

// Avec exemptions (webhooks, APIs)
new CsrfMiddleware($csrfManager, exemptPaths: ['/api/', '/webhook/'])
```

Dans Twig :

```twig
<form method="POST" action="/login">
    {{ csrf_field() }}
    ...
</form>
```

Pour les requêtes AJAX :

```html
<meta name="csrf-token" content="{{ csrf_token() }}">
```

```js
fetch('/api/data', {
    method: 'POST',
    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
    body: JSON.stringify(data),
});
```

---

### Debug Toolbar

Activée automatiquement quand `APP_DEBUG=true`.

Injectée avant `</body>` sur toutes les réponses HTML, la toolbar affiche :

- **Requête** — méthode, URL, durée totale
- **Requêtes SQL** — chaque requête avec ses paramètres et son temps d'exécution
- **Logs** — tous les messages enregistrés durant la requête
- **Mémoire** — pic d'utilisation mémoire

```env
APP_DEBUG=true   # active la toolbar
APP_DEBUG=false  # désactivée en production
```

---

## Console

```bash
# Génération de code
php bin/console make:entity     NomEntite
php bin/console make:migration  NomMigration
php bin/console make:controller NomController

# Migrations
php bin/console migrate
php bin/console migrate:status
php bin/console migrate:rollback

# Queue
php bin/console queue:work
php bin/console queue:work --once
php bin/console queue:flush
```

---

## Tests

```bash
composer test
# ou
vendor/bin/phpunit --testdox
```

**516 tests · 839 assertions** — tout vert sur PHP 8.1 / 8.2 / 8.3 / 8.4.

---

## Licence

MIT — [IMAMx39](https://github.com/IMAMx39)
