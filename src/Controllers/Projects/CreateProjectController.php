<?php declare(strict_types=1);

namespace Reconmap\Controllers\Projects;

use Psr\Http\Message\ServerRequestInterface;
use Reconmap\Controllers\Controller;
use Reconmap\Models\Project;
use Reconmap\Repositories\ProjectRepository;

class CreateProjectController extends Controller
{
    public function __construct(private ProjectRepository $projectRepository)
    {
    }

    public function __invoke(ServerRequestInterface $request): array
    {
        $project = $this->getJsonBodyDecodedAsClass($request, new Project());
        $project->creator_uid = $request->getAttribute('userId');

        $this->projectRepository->insert($project);

        return ['success' => true];
    }
}
