<?php
/**
 * This file contains only the PagesController class.
 */

namespace AppBundle\Controller;

use DateTime;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Xtools\ProjectRepository;

/**
 * This controller serves the Pages tool.
 */
class PagesController extends Controller
{

    /**
     * Get the tool's shortname.
     * @return string
     */
    public function getToolShortname()
    {
        return 'pages';
    }

    /**
     * Display the form.
     * @Route("/pages", name="pages")
     * @Route("/pages", name="Pages")
     * @Route("/pages/", name="PagesSlash")
     * @Route("/pages/index.php", name="PagesIndexPhp")
     * @Route("/pages/{project}", name="PagesProject")
     * @param string $project The project domain name.
     * @return Response
     */
    public function indexAction($project = null)
    {
        // Grab the request object, grab the values out of it.
        $request = Request::createFromGlobals();

        $projectQuery = $request->query->get('project');
        $username = $request->query->get('username', $request->query->get('user'));
        $namespace = $request->query->get('namespace');
        $redirects = $request->query->get('redirects');

        // if values for required parameters are present, redirect to result action
        if ($projectQuery != "" && $username != "" && $namespace != "" && $redirects != "") {
            return $this->redirectToRoute("PagesResult", [
                'project'=>$projectQuery,
                'username' => $username,
                'namespace'=>$namespace,
                'redirects'=>$redirects,
            ]);
        } elseif ($projectQuery != "" && $username != "" && $namespace != "") {
            return $this->redirectToRoute("PagesResult", [
                'project'=>$projectQuery,
                'username' => $username,
                'namespace'=>$namespace,
            ]);
        } elseif ($projectQuery != "" && $username != "" && $redirects != "") {
            return $this->redirectToRoute("PagesResult", [
                'project'=>$projectQuery,
                'username' => $username,
                'redirects'=>$redirects,
            ]);
        } elseif ($projectQuery != "" && $username != "") {
            return $this->redirectToRoute("PagesResult", [
                'project'=>$projectQuery,
                'username' => $username,
            ]);
        } elseif ($projectQuery != "") {
            return $this->redirectToRoute("PagesProject", [ 'project'=>$projectQuery ]);
        }

        // set default wiki so we can populate the namespace selector
        if (!$project) {
            $project = $this->getParameter('default_project');
        }

        $projectData = ProjectRepository::getProject($project, $this->container);

        $namespaces = null;

        if ($projectData->exists()) {
            $namespaces = $projectData->getNamespaces();
        }

        // Otherwise fall through.
        return $this->render('pages/index.html.twig', [
            'xtPageTitle' => 'tool-pages',
            'xtSubtitle' => 'tool-pages-desc',
            'xtPage' => 'pages',
            'project' => $projectData,
            'namespaces' => $namespaces,
        ]);
    }

    /**
     * Display the results.
     * @Route("/pages/{project}/{username}/{namespace}/{redirects}", name="PagesResult")
     * @param string $project The project domain name.
     * @param string $username The username.
     * @param string $namespace The ID of the namespace.
     * @param string $redirects Whether to follow redirects or not.
     * @return RedirectResponse|Response
     */
    public function resultAction($project, $username, $namespace = "0", $redirects = "noredirects")
    {
        $lh = $this->get("app.labs_helper");

        $api = $this->get("app.api_helper");

        $username = ucfirst($username);

        $projectData = ProjectRepository::getProject($project, $this->container);

        // If the project exists, actually populate the values
        if (!$projectData->exists()) {
            $this->addFlash("notice", ["invalid-project", $project]);
            return $this->redirectToRoute("pages");
        }

        $dbName = $projectData->getDatabaseName();
        $projectUrl = $projectData->getUrl();

        $user_id = 0;

        $userTable = $lh->getTable("user", $dbName);
        $pageTable = $lh->getTable("page", $dbName);
        $pageAssessmentsTable = $lh->getTable("page_assessments", $dbName);
        $revisionTable = $lh->getTable("revision", $dbName);
        $archiveTable = $lh->getTable("archive", $dbName);
        $logTable = $lh->getTable("logging", $dbName, "userindex");

        // Grab the connection to the replica database (which is separate from the above)
        $conn = $this->get('doctrine')->getManager("replicas")->getConnection();

        // Prepare the query and execute
        $resultQuery = $conn->prepare("
			SELECT 'id' AS source, user_id AS value FROM $userTable WHERE user_name = :username
            ");

        $resultQuery->bindParam("username", $username);
        $resultQuery->execute();

        $result = $resultQuery->fetchAll();

        if (isset($result[0]["value"])) {
            $user_id = $result[0]["value"];
        }

        $namespaceConditionArc = "";
        $namespaceConditionRev = "";

        if ($namespace != "all") {
            $namespaceConditionRev = " AND page_namespace = '".intval($namespace)."' ";
            $namespaceConditionArc = " AND ar_namespace = '".intval($namespace)."' ";
        }

        $summaryColumns = ['namespace']; // what columns to show in namespace totals table
        $redirectCondition = "";
        if ($redirects == "onlyredirects") {
            // don't show redundant pages column if only getting data on redirects
            $summaryColumns[] = 'redirects';

            $redirectCondition = " AND page_is_redirect = '1' ";
        } elseif ($redirects == "noredirects") {
            // don't show redundant redirects column if only getting data on non-redirects
            $summaryColumns[] = 'pages';

            $redirectCondition = " AND page_is_redirect = '0' ";
        } else {
            // order is important here
            $summaryColumns[] = 'pages';
            $summaryColumns[] = 'redirects';
        }
        $summaryColumns[] = 'deleted'; // always show deleted column

        if ($user_id == 0) { // IP Editor or undefined username.
            $whereRev = " rev_user_text = '$username' AND rev_user = '0' ";
            $whereArc = " ar_user_text = '$username' AND ar_user = '0' ";
            $having = " rev_user_text = '$username' ";
        } else {
            $whereRev = " rev_user = '$user_id' AND rev_timestamp > 1 ";
            $whereArc = " ar_user = '$user_id' AND ar_timestamp > 1 ";
            $having = " rev_user = '$user_id' ";
        }

        $hasPageAssessments = $lh->isLabs() && $api->projectHasPageAssessments($project);
        $paSelects = $hasPageAssessments ? ', pa_class, pa_importance, pa_page_revision' : '';
        $paSelectsArchive = $hasPageAssessments ?
            ', NULL AS pa_class, NULL AS pa_page_id, NULL AS pa_page_revision'
            : '';
        $paJoin = $hasPageAssessments ? "LEFT JOIN $pageAssessmentsTable ON rev_page = pa_page_id" : '';

        $stmt = "
            (SELECT DISTINCT page_namespace AS namespace, 'rev' AS type, page_title AS page_title,
                page_len, page_is_redirect AS page_is_redirect, rev_timestamp AS timestamp,
                rev_user, rev_user_text, rev_len, rev_id $paSelects
            FROM $pageTable
            JOIN $revisionTable ON page_id = rev_page
            $paJoin
            WHERE $whereRev AND rev_parent_id = '0' $namespaceConditionRev $redirectCondition
            " . ($hasPageAssessments ? "GROUP BY rev_page" : "") . "
            )

            UNION

            (SELECT a.ar_namespace AS namespace, 'arc' AS type, a.ar_title AS page_title,
                0 AS page_len, '0' AS page_is_redirect, min(a.ar_timestamp) AS timestamp,
                a.ar_user AS rev_user, a.ar_user_text AS rev_user_text, a.ar_len AS rev_len,
                a.ar_rev_id AS rev_id $paSelectsArchive
            FROM $archiveTable a
            JOIN
            (
                SELECT b.ar_namespace, b.ar_title
                FROM $archiveTable AS b
                LEFT JOIN $logTable ON log_namespace = b.ar_namespace AND log_title = b.ar_title
                    AND log_user = b.ar_user AND (log_action = 'move' or log_action = 'move_redir')
                WHERE $whereArc AND b.ar_parent_id = '0' $namespaceConditionArc AND log_action IS NULL
            ) AS c ON c.ar_namespace= a.ar_namespace AND c.ar_title = a.ar_title
            GROUP BY a.ar_namespace, a.ar_title
            HAVING $having
            )
            ";
        $resultQuery = $conn->prepare($stmt);
        $resultQuery->execute();

        $result = $resultQuery->fetchAll();

        $pagesByNamespaceByDate = [];
        $pageTitles = [];
        $countsByNamespace = [];
        $total = 0;
        $redirectTotal = 0;
        $deletedTotal = 0;

        foreach ($result as $row) {
            $datetime = DateTime::createFromFormat('YmdHis', $row["timestamp"]);
            $datetimeKey = $datetime->format('Ymdhi');
            $datetimeHuman = $datetime->format('Y-m-d H:i');

            $pageData = array_merge($row, [
                "human_time" => $datetimeHuman,
                "page_title" => str_replace('_', ' ', $row["page_title"])
            ]);

            if ($hasPageAssessments) {
                $pageData["badge"] = $api->getAssessmentBadgeURL($project, $pageData["pa_class"]);
            }

            $pagesByNamespaceByDate[$row["namespace"]][$datetimeKey][] = $pageData;

            $pageTitles[] = $row["page_title"];

            // Totals
            if (isset($countsByNamespace[$row["namespace"]]["total"])) {
                $countsByNamespace[$row["namespace"]]["total"]++;
            } else {
                $countsByNamespace[$row["namespace"]]["total"] = 1;
                $countsByNamespace[$row["namespace"]]["redirect"] = 0;
                $countsByNamespace[$row["namespace"]]["deleted"] = 0;
            }
            $total++;

            if ($row["page_is_redirect"]) {
                $redirectTotal++;
                // Redirects
                if (isset($countsByNamespace[$row["namespace"]]["redirect"])) {
                    $countsByNamespace[$row["namespace"]]["redirect"]++;
                } else {
                    $countsByNamespace[$row["namespace"]]["redirect"] = 1;
                }
            }

            if ($row["type"] === "arc") {
                $deletedTotal++;
                // Deleted
                if (isset($countsByNamespace[$row["namespace"]]["deleted"])) {
                    $countsByNamespace[$row["namespace"]]["deleted"]++;
                } else {
                    $countsByNamespace[$row["namespace"]]["deleted"] = 1;
                }
            }
        }

        if ($total < 1) {
            $this->addFlash("notice", [ "no-result", $username ]);
            return $this->redirectToRoute("PagesProject", [ "project"=>$project ]);
        }

        ksort($pagesByNamespaceByDate);
        ksort($countsByNamespace);

        foreach (array_keys($pagesByNamespaceByDate) as $key) {
            krsort($pagesByNamespaceByDate[$key]);
        }

        // Retrieve the namespaces
        $namespaces = $api->namespaces($project);

        // Assign the values and display the template
        return $this->render('pages/result.html.twig', [
            'xtPage' => 'pages',
            'xtTitle' => $username,
            'project' => $projectData,
            'username' => $username, // FIXME: should be User object
            'namespace' => $namespace,
            'redirect' => $redirects,
            'summaryColumns' => $summaryColumns,

            'namespaces' => $namespaces,

            'pages' => $pagesByNamespaceByDate,
            'count' => $countsByNamespace,

            'total' => $total,
            'redirectTotal' => $redirectTotal,
            'deletedTotal' => $deletedTotal,

            'hasPageAssessments' => $hasPageAssessments,
        ]);
    }
}
