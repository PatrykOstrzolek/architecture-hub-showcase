<?php

declare(strict_types=1);

namespace App\Assessment\Infrastructure\Sulu\Admin;

use Sulu\Bundle\AdminBundle\Admin\Admin;
use Sulu\Bundle\AdminBundle\Admin\Navigation\NavigationItem;
use Sulu\Bundle\AdminBundle\Admin\Navigation\NavigationItemCollection;
use Sulu\Bundle\AdminBundle\Admin\View\ToolbarAction;
use Sulu\Bundle\AdminBundle\Admin\View\ViewBuilderFactoryInterface;
use Sulu\Bundle\AdminBundle\Admin\View\ViewCollection;

/**
 * Registers the "Questions" and "Question Sets" admin sections under a
 * top-level "Assessment" navigation item — this project's first Sulu Admin
 * extension not backed by a page template. One Admin class for both
 * resources since they're one bounded context (Assessment), mirroring how
 * Sulu's own ContactAdmin/MediaAdmin register a top-level item with child
 * views. No fine-grained SecurityChecker permission gating (unlike Sulu's
 * own TagAdmin) — this project has a single admin role, so the standing
 * `^/admin -> ROLE_USER` firewall rule is the only gate needed, same as
 * every other admin screen here. See ADR-0014.
 */
class AssessmentAdmin extends Admin
{
    public const QUESTION_LIST_VIEW = 'app_assessment.question_list';
    public const QUESTION_ADD_FORM_VIEW = 'app_assessment.question_add_form';
    public const QUESTION_EDIT_FORM_VIEW = 'app_assessment.question_edit_form';

    public const QUESTION_SET_LIST_VIEW = 'app_assessment.question_set_list';
    public const QUESTION_SET_ADD_FORM_VIEW = 'app_assessment.question_set_add_form';
    public const QUESTION_SET_EDIT_FORM_VIEW = 'app_assessment.question_set_edit_form';

    public const ATTEMPT_LIST_VIEW = 'app_assessment.attempt_list';

    public function __construct(private ViewBuilderFactoryInterface $viewBuilderFactory)
    {
    }

    public function configureNavigationItems(NavigationItemCollection $navigationItemCollection): void
    {
        // Translation keys resolved via translations/admin.en.json (domain
        // "admin"), matching Sulu's own sulu_page.*/sulu_admin.* convention.
        $assessment = new NavigationItem('app.assessment');
        $assessment->setPosition(40);
        $assessment->setIcon('su-pen');

        $questions = new NavigationItem('app.questions');
        $questions->setPosition(10);
        $questions->setView(self::QUESTION_LIST_VIEW);
        $assessment->addChild($questions);

        $questionSets = new NavigationItem('app.question_sets');
        $questionSets->setPosition(20);
        $questionSets->setView(self::QUESTION_SET_LIST_VIEW);
        $assessment->addChild($questionSets);

        $attempts = new NavigationItem('app.attempts');
        $attempts->setPosition(30);
        $attempts->setView(self::ATTEMPT_LIST_VIEW);
        $assessment->addChild($attempts);

        $navigationItemCollection->add($assessment);
    }

    public function configureViews(ViewCollection $viewCollection): void
    {
        $listToolbarActions = [new ToolbarAction('sulu_admin.add'), new ToolbarAction('sulu_admin.delete')];
        $formToolbarActions = [new ToolbarAction('sulu_admin.save'), new ToolbarAction('sulu_admin.delete')];

        $this->configureResourceViews(
            $viewCollection,
            resourceKey: 'questions',
            listKey: 'questions',
            formKey: 'question_details',
            path: '/questions',
            title: 'app.questions',
            titleProperty: 'text',
            listView: self::QUESTION_LIST_VIEW,
            addFormView: self::QUESTION_ADD_FORM_VIEW,
            editFormView: self::QUESTION_EDIT_FORM_VIEW,
            listToolbarActions: $listToolbarActions,
            formToolbarActions: $formToolbarActions,
        );

        $this->configureResourceViews(
            $viewCollection,
            resourceKey: 'question_sets',
            listKey: 'question_sets',
            formKey: 'question_set_details',
            path: '/question-sets',
            title: 'app.question_sets',
            titleProperty: 'title',
            listView: self::QUESTION_SET_LIST_VIEW,
            addFormView: self::QUESTION_SET_ADD_FORM_VIEW,
            editFormView: self::QUESTION_SET_EDIT_FORM_VIEW,
            listToolbarActions: $listToolbarActions,
            formToolbarActions: $formToolbarActions,
        );

        // Read-only: no add/edit form views. Attempts are written once, by
        // the grading flow (ADR-0012), never authored or edited in admin.
        $viewCollection->add(
            $this->viewBuilderFactory->createListViewBuilder(self::ATTEMPT_LIST_VIEW, '/attempts')
                ->setResourceKey('attempts')
                ->setListKey('attempts')
                ->setTitle('app.attempts')
                ->addListAdapters(['table'])
                ->addToolbarActions([new ToolbarAction('sulu_admin.delete')]),
        );
    }

    /**
     * @param list<ToolbarAction> $listToolbarActions
     * @param list<ToolbarAction> $formToolbarActions
     */
    private function configureResourceViews(
        ViewCollection $viewCollection,
        string $resourceKey,
        string $listKey,
        string $formKey,
        string $path,
        string $title,
        string $titleProperty,
        string $listView,
        string $addFormView,
        string $editFormView,
        array $listToolbarActions,
        array $formToolbarActions,
    ): void {
        $viewCollection->add(
            $this->viewBuilderFactory->createListViewBuilder($listView, $path)
                ->setResourceKey($resourceKey)
                ->setListKey($listKey)
                ->setTitle($title)
                ->addListAdapters(['table'])
                ->setAddView($addFormView)
                ->setEditView($editFormView)
                ->addToolbarActions($listToolbarActions),
        );
        $viewCollection->add(
            $this->viewBuilderFactory->createResourceTabViewBuilder($addFormView, $path . '/add')
                ->setResourceKey($resourceKey)
                ->setBackView($listView),
        );
        $viewCollection->add(
            $this->viewBuilderFactory->createFormViewBuilder($addFormView . '.details', '/details')
                ->setResourceKey($resourceKey)
                ->setFormKey($formKey)
                ->setTabTitle('sulu_admin.details')
                ->setEditView($editFormView)
                ->addToolbarActions($formToolbarActions)
                ->setParent($addFormView),
        );
        $viewCollection->add(
            $this->viewBuilderFactory->createResourceTabViewBuilder($editFormView, $path . '/:id')
                ->setResourceKey($resourceKey)
                ->setBackView($listView)
                ->setTitleProperty($titleProperty),
        );
        $viewCollection->add(
            $this->viewBuilderFactory->createFormViewBuilder($editFormView . '.details', '/details')
                ->setResourceKey($resourceKey)
                ->setFormKey($formKey)
                ->setTabTitle('sulu_admin.details')
                ->addToolbarActions($formToolbarActions)
                ->setParent($editFormView),
        );
    }
}
