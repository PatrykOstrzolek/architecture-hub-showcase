<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Seeds the demo QuestionSet/Question/Option data for the Assessment bounded
 * context (ADR-0014) and links each learning path's `exercise` page to its
 * set. The three exercise pages were seeded before this feature existed and
 * were never re-published with a questionSet selected, so their
 * `content.questionSet` resolves to null in production — the pages render
 * with a title/intro but no questions (see always-after-deploy investigation).
 *
 * The UPDATE only touches dimension-content rows that don't already carry a
 * `questionSet` key, so this is safe to run against an environment (e.g.
 * local dev) where an admin has already linked it by hand.
 */
final class Version20260704090000 extends AbstractMigration
{
    private const DISTRIBUTED_SYSTEMS_PAGE_UUID = '019f1daf-2899-7558-b566-68430b891a6c';
    private const DOMAIN_DRIVEN_DESIGN_PAGE_UUID = '019f1daf-28dd-7ef0-ad6b-c6265c14b783';
    private const ARCHITECTURE_PATTERNS_PAGE_UUID = '019f1daf-28f5-7ca0-975a-5fedb42e4b69';

    public function getDescription(): string
    {
        return 'Seed demo QuestionSets for the 3 learning-path exercise pages and link them via pa_page_dimension_contents.templatedata (ADR-0014).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("INSERT INTO assessment_question_set (id, title) VALUES (2, 'Distributed Systems Fundamentals: Check Your Understanding Quiz')");
        $this->addSql("INSERT INTO assessment_question_set (id, title) VALUES (3, 'Domain-Driven Design: Check Your Understanding Quiz')");
        $this->addSql("INSERT INTO assessment_question_set (id, title) VALUES (4, 'Architecture Patterns: Check Your Understanding Quiz')");

        $this->addSql("INSERT INTO assessment_question (id, text, explanation) VALUES (3, 'During a network partition, the CAP theorem forces a distributed system to choose between which two guarantees?', 'Partition tolerance cannot be sacrificed in a real distributed system, since network partitions will happen. When one occurs, the system must trade off between returning consistent data (which may require blocking) and remaining available (which may return stale data).')");
        $this->addSql("INSERT INTO assessment_question (id, text, explanation) VALUES (4, 'What guarantee does an eventually consistent system actually provide?', 'Eventual consistency only promises convergence once writes stop — it makes no promise about how long that takes or what a read returns in the meantime.')");
        $this->addSql("INSERT INTO assessment_question (id, text, explanation) VALUES (5, 'Which state does a circuit breaker enter to stop sending requests to a failing downstream service?', 'Open short-circuits calls immediately instead of waiting on a doomed request. After a cooldown period the breaker moves to half-open to test whether the downstream service has recovered.')");
        $this->addSql("INSERT INTO assessment_question (id, text, explanation) VALUES (6, 'How does the Saga pattern keep data consistent across services without a two-phase commit?', 'Each step commits its own local transaction independently; if a later step fails, previously completed steps are undone via explicit compensating actions rather than a distributed lock.')");
        $this->addSql("INSERT INTO assessment_question (id, text, explanation) VALUES (7, 'What problem does the Outbox pattern solve?', 'The outgoing event is written to an outbox table in the same local transaction as the business change, then a separate relay process publishes it — avoiding the dual-write problem without needing two-phase commit.')");
        $this->addSql("INSERT INTO assessment_question (id, text, explanation) VALUES (8, 'What is a ''Bounded Context'' in Domain-Driven Design?', 'A Bounded Context defines where a specific model and its ubiquitous language are valid — the same term (e.g. \"Order\") can mean something different in another context.')");
        $this->addSql("INSERT INTO assessment_question (id, text, explanation) VALUES (9, 'What is the primary responsibility of an Aggregate Root?', 'External code only ever references the aggregate root, which is responsible for keeping the whole cluster of objects inside it consistent.')");
        $this->addSql("INSERT INTO assessment_question (id, text, explanation) VALUES (10, 'Which characteristic distinguishes a Value Object from an Entity in DDD?', 'Two Value Objects with the same attribute values are considered equal and interchangeable — unlike an Entity, which retains a distinct identity even if its attributes change.')");
        $this->addSql("INSERT INTO assessment_question (id, text, explanation) VALUES (11, 'What does CQRS (Command Query Responsibility Segregation) separate?', 'CQRS lets the write side optimize for enforcing business rules and the read side optimize for query shape/performance, instead of forcing one model to serve both purposes.')");
        $this->addSql("INSERT INTO assessment_question (id, text, explanation) VALUES (12, 'In Event Sourcing, how is an entity''s current state determined?', 'The event log is the source of truth; current state is a projection obtained by folding all events in order (snapshots are an optional optimization, not a replacement for the log).')");
        $this->addSql("INSERT INTO assessment_question (id, text, explanation) VALUES (13, 'What is the main purpose of the Repository pattern?', 'A repository lets domain code work with aggregates as if they were an in-memory collection, keeping persistence concerns out of the domain model.')");
        $this->addSql("INSERT INTO assessment_question (id, text, explanation) VALUES (14, 'In Hexagonal Architecture (Ports and Adapters), what is the role of a ''port''?', 'Ports are interfaces owned by the application core; adapters (HTTP controllers, database repositories, message consumers, ...) implement or call them, keeping the core decoupled from any specific technology.')");
        $this->addSql("INSERT INTO assessment_question (id, text, explanation) VALUES (15, 'What does the Dependency Rule in Clean Architecture state?', 'Inner layers (business rules) must know nothing about outer layers (frameworks, UI, database) — dependencies always point toward the center, never outward.')");
        $this->addSql("INSERT INTO assessment_question (id, text, explanation) VALUES (16, 'Which of these is a commonly cited drawback of a microservices architecture?', 'Splitting a system into independently deployable services trades a simpler deployment model for the operational overhead of distributed tracing, network failure handling, and cross-service versioning.')");
        $this->addSql("INSERT INTO assessment_question (id, text, explanation) VALUES (17, 'What is a reasonable signal that part of a monolith should be split into a separate service?', 'Splitting is justified by a concrete constraint — e.g. one module needs independent scaling or a separate release cadence — not by novelty for its own sake.')");
        $this->addSql("INSERT INTO assessment_question (id, text, explanation) VALUES (18, 'What is a primary responsibility of an API Gateway in a service-oriented architecture?', 'The gateway sits in front of backing services, giving clients one endpoint while centralizing concerns (auth, rate limiting, aggregation) that would otherwise be duplicated in every service.')");

        $this->addSql("INSERT INTO assessment_option (id, text, is_correct, \"position\", question_id) VALUES (6, 'Consistency and Availability', true, 0, 3)");
        $this->addSql("INSERT INTO assessment_option (id, text, is_correct, \"position\", question_id) VALUES (7, 'Consistency and Partition Tolerance', false, 1, 3)");
        $this->addSql("INSERT INTO assessment_option (id, text, is_correct, \"position\", question_id) VALUES (8, 'Availability and Partition Tolerance', false, 2, 3)");
        $this->addSql("INSERT INTO assessment_option (id, text, is_correct, \"position\", question_id) VALUES (9, 'None — all three can always be guaranteed', false, 3, 3)");
        $this->addSql("INSERT INTO assessment_option (id, text, is_correct, \"position\", question_id) VALUES (10, 'Every read immediately returns the most recent write', false, 0, 4)");
        $this->addSql("INSERT INTO assessment_option (id, text, is_correct, \"position\", question_id) VALUES (11, 'If no new updates are made, all replicas will eventually converge to the same value', true, 1, 4)");
        $this->addSql("INSERT INTO assessment_option (id, text, is_correct, \"position\", question_id) VALUES (12, 'Writes are rejected outright during any partition', false, 2, 4)");
        $this->addSql("INSERT INTO assessment_option (id, text, is_correct, \"position\", question_id) VALUES (13, 'Consistency is guaranteed, but only for the client that performed the write', false, 3, 4)");
        $this->addSql("INSERT INTO assessment_option (id, text, is_correct, \"position\", question_id) VALUES (14, 'Closed', false, 0, 5)");
        $this->addSql("INSERT INTO assessment_option (id, text, is_correct, \"position\", question_id) VALUES (15, 'Half-open', false, 1, 5)");
        $this->addSql("INSERT INTO assessment_option (id, text, is_correct, \"position\", question_id) VALUES (16, 'Open', true, 2, 5)");
        $this->addSql("INSERT INTO assessment_option (id, text, is_correct, \"position\", question_id) VALUES (17, 'Paused', false, 3, 5)");
        $this->addSql("INSERT INTO assessment_option (id, text, is_correct, \"position\", question_id) VALUES (18, 'By locking every resource involved until the whole transaction completes', false, 0, 6)");
        $this->addSql("INSERT INTO assessment_option (id, text, is_correct, \"position\", question_id) VALUES (19, 'By running a sequence of local transactions, with compensating actions to undo prior steps if a later step fails', true, 1, 6)");
        $this->addSql("INSERT INTO assessment_option (id, text, is_correct, \"position\", question_id) VALUES (20, 'By retrying failed steps forever without ever rolling back', false, 2, 6)");
        $this->addSql("INSERT INTO assessment_option (id, text, is_correct, \"position\", question_id) VALUES (21, 'By requiring every service to share one physical database', false, 3, 6)");
        $this->addSql("INSERT INTO assessment_option (id, text, is_correct, \"position\", question_id) VALUES (22, 'It replaces the need for a message broker entirely', false, 0, 7)");
        $this->addSql("INSERT INTO assessment_option (id, text, is_correct, \"position\", question_id) VALUES (23, 'It guarantees exactly-once delivery with no deduplication needed downstream', false, 1, 7)");
        $this->addSql("INSERT INTO assessment_option (id, text, is_correct, \"position\", question_id) VALUES (24, 'It guarantees at-least-once event delivery by writing the event in the same database transaction as the business data change', true, 2, 7)");
        $this->addSql("INSERT INTO assessment_option (id, text, is_correct, \"position\", question_id) VALUES (25, 'It prevents duplicate messages purely through network-level retries', false, 3, 7)");
        $this->addSql("INSERT INTO assessment_option (id, text, is_correct, \"position\", question_id) VALUES (26, 'A microservice''s Docker container', false, 0, 8)");
        $this->addSql("INSERT INTO assessment_option (id, text, is_correct, \"position\", question_id) VALUES (27, 'A shared kernel used identically by every subdomain', false, 1, 8)");
        $this->addSql("INSERT INTO assessment_option (id, text, is_correct, \"position\", question_id) VALUES (28, 'A physical database boundary enforced by a DBA', false, 2, 8)");
        $this->addSql("INSERT INTO assessment_option (id, text, is_correct, \"position\", question_id) VALUES (29, 'An explicit boundary within which a particular domain model applies with a consistent, unambiguous meaning', true, 3, 8)");
        $this->addSql("INSERT INTO assessment_option (id, text, is_correct, \"position\", question_id) VALUES (30, 'To expose every internal entity''s setters directly, for flexibility', false, 0, 9)");
        $this->addSql("INSERT INTO assessment_option (id, text, is_correct, \"position\", question_id) VALUES (31, 'To act as the single entry point that enforces invariants across the aggregate''s internal entities', true, 1, 9)");
        $this->addSql("INSERT INTO assessment_option (id, text, is_correct, \"position\", question_id) VALUES (32, 'To store the aggregate''s data spread across multiple databases for scalability', false, 2, 9)");
        $this->addSql("INSERT INTO assessment_option (id, text, is_correct, \"position\", question_id) VALUES (33, 'To eliminate the need for a repository', false, 3, 9)");
        $this->addSql("INSERT INTO assessment_option (id, text, is_correct, \"position\", question_id) VALUES (34, 'A Value Object has a unique identity that is tracked over its lifetime', false, 0, 10)");
        $this->addSql("INSERT INTO assessment_option (id, text, is_correct, \"position\", question_id) VALUES (35, 'A Value Object must always be persisted in its own dedicated table', false, 1, 10)");
        $this->addSql("INSERT INTO assessment_option (id, text, is_correct, \"position\", question_id) VALUES (36, 'A Value Object is defined entirely by its attributes, is immutable, and is interchangeable with another instance holding equal attributes', true, 2, 10)");
        $this->addSql("INSERT INTO assessment_option (id, text, is_correct, \"position\", question_id) VALUES (37, 'A Value Object can only ever wrap a single primitive type', false, 3, 10)");
        $this->addSql("INSERT INTO assessment_option (id, text, is_correct, \"position\", question_id) VALUES (38, 'Synchronous APIs from asynchronous APIs', false, 0, 11)");
        $this->addSql("INSERT INTO assessment_option (id, text, is_correct, \"position\", question_id) VALUES (39, 'The model used for writes (commands) from the model used for reads (queries)', true, 1, 11)");
        $this->addSql("INSERT INTO assessment_option (id, text, is_correct, \"position\", question_id) VALUES (40, 'Unit tests from integration tests', false, 2, 11)");
        $this->addSql("INSERT INTO assessment_option (id, text, is_correct, \"position\", question_id) VALUES (41, 'Frontend code from backend code', false, 3, 11)");
        $this->addSql("INSERT INTO assessment_option (id, text, is_correct, \"position\", question_id) VALUES (42, 'It''s derived by replaying the full sequence of stored events for that entity', true, 0, 12)");
        $this->addSql("INSERT INTO assessment_option (id, text, is_correct, \"position\", question_id) VALUES (43, 'It''s read directly from a single row that gets overwritten in place', false, 1, 12)");
        $this->addSql("INSERT INTO assessment_option (id, text, is_correct, \"position\", question_id) VALUES (44, 'It''s cached once and never recalculated again', false, 2, 12)");
        $this->addSql("INSERT INTO assessment_option (id, text, is_correct, \"position\", question_id) VALUES (45, 'It''s taken from the most recent snapshot only, with all prior events discarded', false, 3, 12)");
        $this->addSql("INSERT INTO assessment_option (id, text, is_correct, \"position\", question_id) VALUES (46, 'To replace the need for an ORM entirely', false, 0, 13)");
        $this->addSql("INSERT INTO assessment_option (id, text, is_correct, \"position\", question_id) VALUES (47, 'To handle HTTP routing for domain entities', false, 1, 13)");
        $this->addSql("INSERT INTO assessment_option (id, text, is_correct, \"position\", question_id) VALUES (48, 'To mediate between the domain model and the data mapping layer, exposing a collection-like interface for retrieving and persisting aggregates', true, 2, 13)");
        $this->addSql("INSERT INTO assessment_option (id, text, is_correct, \"position\", question_id) VALUES (49, 'To expose raw SQL queries directly to the domain layer', false, 3, 13)");
        $this->addSql("INSERT INTO assessment_option (id, text, is_correct, \"position\", question_id) VALUES (50, 'A network port number the service listens on', false, 0, 14)");
        $this->addSql("INSERT INTO assessment_option (id, text, is_correct, \"position\", question_id) VALUES (51, 'A database connection pool', false, 1, 14)");
        $this->addSql("INSERT INTO assessment_option (id, text, is_correct, \"position\", question_id) VALUES (52, 'An interface defined by the application core that adapters implement to connect it to the outside world', true, 2, 14)");
        $this->addSql("INSERT INTO assessment_option (id, text, is_correct, \"position\", question_id) VALUES (53, 'A physical slot in a server rack', false, 3, 14)");
        $this->addSql("INSERT INTO assessment_option (id, text, is_correct, \"position\", question_id) VALUES (54, 'Every class must depend on at least one interface', false, 0, 15)");
        $this->addSql("INSERT INTO assessment_option (id, text, is_correct, \"position\", question_id) VALUES (55, 'Outer layers may never be covered by tests', false, 1, 15)");
        $this->addSql("INSERT INTO assessment_option (id, text, is_correct, \"position\", question_id) VALUES (56, 'All dependencies must be resolved through a global service locator', false, 2, 15)");
        $this->addSql("INSERT INTO assessment_option (id, text, is_correct, \"position\", question_id) VALUES (57, 'Source code dependencies must point only inward, toward higher-level policies', true, 3, 15)");
        $this->addSql("INSERT INTO assessment_option (id, text, is_correct, \"position\", question_id) VALUES (58, 'Inability to scale individual components independently', false, 0, 16)");
        $this->addSql("INSERT INTO assessment_option (id, text, is_correct, \"position\", question_id) VALUES (59, 'Being forced onto a single shared database for all services', false, 1, 16)");
        $this->addSql("INSERT INTO assessment_option (id, text, is_correct, \"position\", question_id) VALUES (60, 'Increased operational complexity from distributed communication, deployment, and monitoring', true, 2, 16)");
        $this->addSql("INSERT INTO assessment_option (id, text, is_correct, \"position\", question_id) VALUES (61, 'Elimination of network latency between components', false, 3, 16)");
        $this->addSql("INSERT INTO assessment_option (id, text, is_correct, \"position\", question_id) VALUES (62, 'The team wants to try a trendy new technology', false, 0, 17)");
        $this->addSql("INSERT INTO assessment_option (id, text, is_correct, \"position\", question_id) VALUES (63, 'A specific module has distinct scaling, deployment, or ownership needs that the monolith is constraining', true, 1, 17)");
        $this->addSql("INSERT INTO assessment_option (id, text, is_correct, \"position\", question_id) VALUES (64, 'The codebase is under 1,000 lines of code', false, 2, 17)");
        $this->addSql("INSERT INTO assessment_option (id, text, is_correct, \"position\", question_id) VALUES (65, 'All modules already deploy together on the same schedule with no friction', false, 3, 17)");
        $this->addSql("INSERT INTO assessment_option (id, text, is_correct, \"position\", question_id) VALUES (66, 'Storing the business data owned by every backing service', false, 0, 18)");
        $this->addSql("INSERT INTO assessment_option (id, text, is_correct, \"position\", question_id) VALUES (67, 'Compiling application code at build time', false, 1, 18)");
        $this->addSql("INSERT INTO assessment_option (id, text, is_correct, \"position\", question_id) VALUES (68, 'Eliminating the need for services to communicate with each other', false, 2, 18)");
        $this->addSql("INSERT INTO assessment_option (id, text, is_correct, \"position\", question_id) VALUES (69, 'Acting as a single entry point that routes requests and can centralize cross-cutting concerns like auth, rate limiting, and response aggregation', true, 3, 18)");

        $this->addSql('INSERT INTO assessment_question_set_item (id, "position", question_set_id, question_id) VALUES (3, 0, 2, 3)');
        $this->addSql('INSERT INTO assessment_question_set_item (id, "position", question_set_id, question_id) VALUES (4, 1, 2, 4)');
        $this->addSql('INSERT INTO assessment_question_set_item (id, "position", question_set_id, question_id) VALUES (5, 2, 2, 5)');
        $this->addSql('INSERT INTO assessment_question_set_item (id, "position", question_set_id, question_id) VALUES (6, 3, 2, 6)');
        $this->addSql('INSERT INTO assessment_question_set_item (id, "position", question_set_id, question_id) VALUES (7, 4, 2, 7)');
        $this->addSql('INSERT INTO assessment_question_set_item (id, "position", question_set_id, question_id) VALUES (8, 0, 3, 8)');
        $this->addSql('INSERT INTO assessment_question_set_item (id, "position", question_set_id, question_id) VALUES (9, 1, 3, 9)');
        $this->addSql('INSERT INTO assessment_question_set_item (id, "position", question_set_id, question_id) VALUES (10, 2, 3, 10)');
        $this->addSql('INSERT INTO assessment_question_set_item (id, "position", question_set_id, question_id) VALUES (11, 3, 3, 11)');
        $this->addSql('INSERT INTO assessment_question_set_item (id, "position", question_set_id, question_id) VALUES (12, 4, 3, 12)');
        $this->addSql('INSERT INTO assessment_question_set_item (id, "position", question_set_id, question_id) VALUES (13, 5, 3, 13)');
        $this->addSql('INSERT INTO assessment_question_set_item (id, "position", question_set_id, question_id) VALUES (14, 0, 4, 14)');
        $this->addSql('INSERT INTO assessment_question_set_item (id, "position", question_set_id, question_id) VALUES (15, 1, 4, 15)');
        $this->addSql('INSERT INTO assessment_question_set_item (id, "position", question_set_id, question_id) VALUES (16, 2, 4, 16)');
        $this->addSql('INSERT INTO assessment_question_set_item (id, "position", question_set_id, question_id) VALUES (17, 3, 4, 17)');
        $this->addSql('INSERT INTO assessment_question_set_item (id, "position", question_set_id, question_id) VALUES (18, 4, 4, 18)');

        // Explicit IDs bypass the identity sequence — bump it so future
        // admin-created QuestionSets/Questions/Options don't collide.
        $this->addSql("SELECT setval(pg_get_serial_sequence('assessment_question_set', 'id'), (SELECT MAX(id) FROM assessment_question_set))");
        $this->addSql("SELECT setval(pg_get_serial_sequence('assessment_question', 'id'), (SELECT MAX(id) FROM assessment_question))");
        $this->addSql("SELECT setval(pg_get_serial_sequence('assessment_option', 'id'), (SELECT MAX(id) FROM assessment_option))");
        $this->addSql("SELECT setval(pg_get_serial_sequence('assessment_question_set_item', 'id'), (SELECT MAX(id) FROM assessment_question_set_item))");

        // jsonb_exists(), not the `?` has-key operator: DBAL/PDO treats a bare
        // `?` in raw SQL as a positional parameter placeholder, not a JSONB
        // operator, and errors with a syntax error at execution time.
        $this->addSql("UPDATE pa_page_dimension_contents SET templatedata = templatedata || jsonb_build_object('questionSet', 2) WHERE pageuuid = '" . self::DISTRIBUTED_SYSTEMS_PAGE_UUID . "' AND templatekey = 'exercise' AND NOT jsonb_exists(templatedata, 'questionSet')");
        $this->addSql("UPDATE pa_page_dimension_contents SET templatedata = templatedata || jsonb_build_object('questionSet', 3) WHERE pageuuid = '" . self::DOMAIN_DRIVEN_DESIGN_PAGE_UUID . "' AND templatekey = 'exercise' AND NOT jsonb_exists(templatedata, 'questionSet')");
        $this->addSql("UPDATE pa_page_dimension_contents SET templatedata = templatedata || jsonb_build_object('questionSet', 4) WHERE pageuuid = '" . self::ARCHITECTURE_PATTERNS_PAGE_UUID . "' AND templatekey = 'exercise' AND NOT jsonb_exists(templatedata, 'questionSet')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE pa_page_dimension_contents SET templatedata = templatedata - 'questionSet' WHERE pageuuid IN ('" . self::DISTRIBUTED_SYSTEMS_PAGE_UUID . "', '" . self::DOMAIN_DRIVEN_DESIGN_PAGE_UUID . "', '" . self::ARCHITECTURE_PATTERNS_PAGE_UUID . "') AND templatekey = 'exercise'");

        $this->addSql('DELETE FROM assessment_question_set_item WHERE question_set_id IN (2, 3, 4)');
        $this->addSql('DELETE FROM assessment_option WHERE question_id BETWEEN 3 AND 18');
        $this->addSql('DELETE FROM assessment_question WHERE id BETWEEN 3 AND 18');
        $this->addSql('DELETE FROM assessment_question_set WHERE id IN (2, 3, 4)');
    }
}
