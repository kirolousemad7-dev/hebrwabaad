<?php

namespace Tests\Unit;

use App\Enums\UserRole;
use App\Support\EmployeeWorkspace;
use PHPUnit\Framework\TestCase;

class EmployeeWorkspaceConfigTest extends TestCase
{
    public function test_unknown_roles_do_not_receive_workspace_capabilities(): void
    {
        $this->assertFalse(UserRole::Owner->usesEmployeeWorkspace());
        $this->assertFalse(UserRole::Customer->usesEmployeeWorkspace());
        $this->assertFalse(UserRole::AdminManager->usesEmployeeWorkspace());
        $this->assertSame([], EmployeeWorkspace::capabilitiesFor(UserRole::AdminManager));
    }

    public function test_workspace_keys_match_task_17_foundation(): void
    {
        $this->assertSame('web-developer', UserRole::WebDeveloper->workspaceKey());
        $this->assertSame('graphic-designer', UserRole::GraphicDesigner->workspaceKey());
        $this->assertSame('marketing', UserRole::MarketingSpecialist->workspaceKey());
        $this->assertSame('event', UserRole::EventSpecialist->workspaceKey());
        $this->assertSame('printing', UserRole::PrintingSpecialist->workspaceKey());
        $this->assertSame('video-editor', UserRole::VideoEditor->workspaceKey());
    }

    public function test_web_developer_widgets_prioritize_tasks_projects_and_deadlines(): void
    {
        $this->assertSame(
            ['overview', 'tasks', 'projects', 'deadlines', 'requirements', 'revisions', 'files', 'messages'],
            EmployeeWorkspace::widgetIdsFor(UserRole::WebDeveloper)
        );
    }

    public function test_web_developer_domains_mark_live_tasks_and_unavailable_project_domains(): void
    {
        $domains = EmployeeWorkspace::domainsFor(UserRole::WebDeveloper);

        $this->assertTrue($domains['tasks']['available']);
        $this->assertTrue($domains['deadlines']['available']);

        $this->assertTrue($domains['projects']['available']);

        $this->assertTrue($domains['files']['available']);

        foreach (['requirements', 'revisions', 'messages'] as $key) {
            $this->assertFalse($domains[$key]['available']);
            $this->assertSame('unavailable', $domains[$key]['status']);
        }
    }

    public function test_role_change_drops_developer_specific_capabilities(): void
    {
        $developer = EmployeeWorkspace::widgetIdsFor(UserRole::WebDeveloper);
        $designer = EmployeeWorkspace::widgetIdsFor(UserRole::GraphicDesigner);

        $this->assertContains('requirements', $developer);
        $this->assertNotContains('design-briefs', $developer);
        $this->assertContains('design-briefs', $designer);
        $this->assertNotContains('requirements', $designer);
        $this->assertContains('revisions', $designer);
    }

    public function test_graphic_designer_widgets_prioritize_projects_briefs_and_reviews(): void
    {
        $this->assertSame(
            ['overview', 'projects', 'design-briefs', 'tasks', 'deadlines', 'files', 'revisions', 'messages'],
            EmployeeWorkspace::widgetIdsFor(UserRole::GraphicDesigner)
        );
    }

    public function test_graphic_designer_domains_mark_live_tasks_and_unavailable_design_domains(): void
    {
        $domains = EmployeeWorkspace::domainsFor(UserRole::GraphicDesigner);

        $this->assertTrue($domains['tasks']['available']);
        $this->assertTrue($domains['deadlines']['available']);

        $this->assertTrue($domains['projects']['available']);

        $this->assertTrue($domains['files']['available']);

        foreach (['design-briefs', 'revisions', 'messages'] as $key) {
            $this->assertFalse($domains[$key]['available']);
            $this->assertSame('unavailable', $domains[$key]['status']);
        }

        $this->assertArrayNotHasKey('requirements', $domains);
    }

    public function test_new_workspace_roles_resolve_to_dedicated_keys(): void
    {
        $this->assertSame('media-buyer', UserRole::MediaBuyer->workspaceKey());
        $this->assertSame('account-manager', UserRole::AccountManager->workspaceKey());
        $this->assertSame('hr', UserRole::Hr->workspaceKey());
        $this->assertTrue(UserRole::AccountManager->canAssignTasks());
        $this->assertFalse(UserRole::Hr->canAssignTasks());
        $this->assertFalse(UserRole::AccountManager->canReceiveAssignedTasks());
        $this->assertTrue(UserRole::MediaBuyer->canReceiveAssignedTasks());
        $this->assertSame(
            ['overview', 'tasks', 'projects', 'campaigns', 'budgets', 'performance', 'deadlines', 'reports'],
            EmployeeWorkspace::widgetIdsFor(UserRole::MediaBuyer)
        );
        $this->assertSame(
            ['overview', 'tasks', 'projects', 'files', 'revisions', 'deadlines', 'messages'],
            EmployeeWorkspace::widgetIdsFor(UserRole::VideoEditor)
        );
        $this->assertSame(
            ['overview', 'projects', 'tasks', 'task-progress', 'deadlines', 'files', 'clients', 'client-requests'],
            EmployeeWorkspace::widgetIdsFor(UserRole::AccountManager)
        );
        $this->assertSame(
            ['overview', 'employees', 'active-employees', 'inactive-employees', 'tasks', 'employee-requests', 'attendance'],
            EmployeeWorkspace::widgetIdsFor(UserRole::Hr)
        );
        $this->assertSame(
            ['overview', 'campaigns', 'tasks', 'projects', 'content', 'deadlines', 'client-requests', 'reports'],
            EmployeeWorkspace::widgetIdsFor(UserRole::MarketingSpecialist)
        );
        $this->assertSame(
            ['overview', 'tasks', 'projects', 'events', 'client-requests', 'deadlines', 'files'],
            EmployeeWorkspace::widgetIdsFor(UserRole::EventSpecialist)
        );
    }
}
