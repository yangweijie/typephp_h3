<?php

/**
 * H3PHP — GUI Application.
 *
 * Qt-based graphical interface for video generation.
 * Provides: model selection, prompt input, parameter controls,
 * progress display, and log output.
 */

namespace H3Php\Gui;

use H3Php\Core\ComfyUISerializer;
use H3Php\Core\DownloadManager;
use H3Php\Core\EnvironmentDetector;
use H3Php\Core\H3NodeLibrary;
use H3Php\Core\ModelManager;
use H3Php\Core\SettingsManager;
use H3Php\Core\WorkflowGraph;
use H3Php\Qt\Application;
use H3Php\Qt\DownloadProgressDialog;
use H3Php\Qt\EnvironmentPanel;
use H3Php\Qt\MainWindow;
use H3Php\Qt\ModelManagerDialog;
use H3Php\Qt\ModelRecommendationDialog;
use H3Php\Qt\NodeCanvas;
use H3Php\Qt\SettingsDialog;
use H3Php\Qt\SetupWizard;

class GuiApp
{
    private Application $qt;

    private MainWindow $window;

    /** First-run setup wizard (null when not in setup) */
    private ?SetupWizard $wizard = null;

    /** Environment panel (lazy; kept referenced to avoid GC of native window) */
    private ?EnvironmentPanel $envPanel = null;

    /** Shared environment detector (reused by wizard + panel) */
    private ?EnvironmentDetector $detector = null;

    /** Model manager dialog (lazy) */
    private ?ModelManagerDialog $modelDialog = null;

    /** Model recommendation dialog (lazy) */
    private ?ModelRecommendationDialog $recDialog = null;

    /** Shared model manager */
    private ?ModelManager $modelManager = null;

    /** Shared download manager */
    private ?DownloadManager $downloadManager = null;

    /** Download progress dialog (lazy) */
    private ?DownloadProgressDialog $downloadDialog = null;

    /** Settings dialog (lazy) */
    private ?SettingsDialog $settingsDialog = null;

    /** Node editor canvas (lazy) */
    private ?NodeCanvas $nodeCanvas = null;

    /** Current workflow graph (lazy, shared by editor + export) */
    private ?WorkflowGraph $workflowGraph = null;

    /** ComfyUI JSON serializer */
    private ?ComfyUISerializer $serializer = null;

    /** Settings manager (loaded in run()) */
    private ?SettingsManager $settings = null;

    /** Widget handles for form fields */
    private int $modelDirEdit = 0;

    private int $browseBtn = 0;

    private int $promptEdit = 0;

    private int $widthCombo = 0;

    private int $heightCombo = 0;

    private int $framesCombo = 0;

    private int $stepsCombo = 0;

    private int $outputEdit = 0;

    private int $generateBtn = 0;

    private int $progressBar = 0;

    private int $logEdit = 0;

    private int $statusLabel = 0;

    /** Generation state */
    private bool $generating = false;

    /** Model directory */
    private string $modelDir = '';

    public function __construct()
    {
        $this->qt = new Application();
        $this->window = new MainWindow();
    }

    /**
     * Set the model directory (pre-populated from CLI).
     * Can be called before run() — UI will be updated in buildUi().
     */
    public function setModelDir(string $dir): void
    {
        $this->modelDir = $dir;
    }

    /**
     * Initialize and run the GUI application.
     */
    public function run(): int
    {
        if (!$this->qt->init()) {
            fprintf(STDERR, "Failed to initialize Qt application\n");
            return 1;
        }

        // QApplication now exists — safe to construct the native window.
        $this->window->create();

        // First-run setup wizard
        $this->settings = new SettingsManager();
        $this->settings->load();
        if (!$this->settings->get('general.setup_completed')) {
            $this->detector = new EnvironmentDetector();
            $this->wizard = new SetupWizard($this->detector, $this->settings);
            $this->qt->on('button_click', [$this, 'onWizardEvent']);
            $this->wizard->show();

            while ($this->wizard->isVisible()) {
                $this->qt->pump();
                usleep(16000);
            }

            $this->wizard = null;
        }

        $this->buildUi();

        // Register event handlers
        $this->qt->on('button_click', [$this, 'onButtonClick']);
        $this->qt->on('menu_click', [$this, 'onMenuClick']);
        $this->qt->on('text_changed', [$this, 'onTextChanged']);
        $this->qt->on('combo_changed', [$this, 'onComboChanged']);
        $this->qt->on('generation_progress', [$this, 'onGenerationProgress']);

        // Event-loop polling: drive downloads and refresh progress UIs
        $this->qt->onTick(function (): void {
            if ($this->downloadManager !== null) {
                $this->downloadManager->process();
            }
            if ($this->downloadDialog !== null && $this->downloadDialog->isVisible()) {
                $this->downloadDialog->update();
            }
            if ($this->modelDialog !== null && $this->modelDialog->isVisible()) {
                $this->modelDialog->updateProgress();
            }
            if ($this->recDialog !== null && $this->recDialog->isVisible()) {
                $this->recDialog->updateProgress();
            }
        });

        return $this->qt->run();
    }

    /**
     * Route wizard button events (callback_id prefixed with 'wizard_').
     */
    public function onWizardEvent(array $event): void
    {
        if (null === $this->wizard || !$this->wizard->isVisible()) {
            return;
        }

        $id = $event['callback_id'] ?? '';
        switch ($id) {
            case 'wizard_back':
                $this->wizard->prevPage();
                break;
            case 'wizard_next':
                $this->wizard->nextPage();
                break;
            case 'wizard_finish':
                $this->wizard->finish();
                break;
            case 'wizard_cancel':
                $this->wizard->close();
                break;
            case 'wizard_apply_lang':
                $this->wizard->applyLanguage();
                break;
        }
    }

    /**
     * Build the main window UI.
     */
    private function buildUi(): void
    {
        $win = $this->window->getHandle();
        $this->window->setTitle('H3PHP — Video Generation');
        $this->window->setSize(900, 700);

        // Main vertical layout
        $mainWidget = qt_widget_create();
        $mainLayout = qt_layout_vbox_create();
        qt_layout_set_spacing($mainLayout, 8);
        qt_layout_set_margins($mainLayout, 12, 12, 12, 12);
        qt_widget_set_layout($mainWidget, $mainLayout);

        // === Model Directory Row ===
        $modelRow = qt_layout_hbox_create();
        qt_layout_set_spacing($modelRow, 6);

        $modelLabel = qt_label_create('Model Dir:');
        qt_layout_add_widget($modelRow, $modelLabel);

        $this->modelDirEdit = qt_line_edit_create('Select model directory...');
        qt_layout_add_widget($modelRow, $this->modelDirEdit);

        $this->browseBtn = qt_button_create('Browse...');
        qt_button_set_on_click($this->browseBtn, 'browse_model');
        qt_layout_add_widget($modelRow, $this->browseBtn);

        qt_layout_add_layout($mainLayout, $modelRow);

        // === Prompt Row ===
        $promptLabel = qt_label_create('Prompt:');
        qt_layout_add_widget($mainLayout, $promptLabel);

        $this->promptEdit = qt_text_edit_create('');
        qt_layout_add_widget($mainLayout, $this->promptEdit);

        // === Parameters Grid (2 columns) ===
        $paramsRow = qt_layout_hbox_create();
        qt_layout_set_spacing($paramsRow, 12);

        // Left column
        $leftCol = qt_layout_vbox_create();
        qt_layout_set_spacing($leftCol, 4);

        $widthLabel = qt_label_create('Width:');
        qt_layout_add_widget($leftCol, $widthLabel);
        $this->widthCombo = qt_combo_box_create(['512', '640', '864', '1024', '1280']);
        qt_combo_box_set_current_index($this->widthCombo, 2); // 864 default
        qt_layout_add_widget($leftCol, $this->widthCombo);

        $heightLabel = qt_label_create('Height:');
        qt_layout_add_widget($leftCol, $heightLabel);
        $this->heightCombo = qt_combo_box_create(['384', '480', '576', '720', '768']);
        qt_combo_box_set_current_index($this->heightCombo, 1); // 480 default
        qt_layout_add_widget($leftCol, $this->heightCombo);

        $framesLabel = qt_label_create('Frames:');
        qt_layout_add_widget($leftCol, $framesLabel);
        $this->framesCombo = qt_combo_box_create(['22', '39', '56', '73', '90', '107']);
        qt_combo_box_set_current_index($this->framesCombo, 2); // 56 default
        qt_layout_add_widget($leftCol, $this->framesCombo);

        qt_layout_add_layout($paramsRow, $leftCol);

        // Right column
        $rightCol = qt_layout_vbox_create();
        qt_layout_set_spacing($rightCol, 4);

        $stepsLabel = qt_label_create('Steps:');
        qt_layout_add_widget($rightCol, $stepsLabel);
        $this->stepsCombo = qt_combo_box_create(['10', '15', '20', '25', '30', '50']);
        qt_combo_box_set_current_index($this->stepsCombo, 2); // 20 default
        qt_layout_add_widget($rightCol, $this->stepsCombo);

        $outputLabel = qt_label_create('Output:');
        qt_layout_add_widget($rightCol, $outputLabel);
        $this->outputEdit = qt_line_edit_create('outputs/h3.mp4');
        qt_layout_add_widget($rightCol, $this->outputEdit);

        $seedLabel = qt_label_create('Seed:');
        qt_layout_add_widget($rightCol, $seedLabel);
        $seedEdit = qt_line_edit_create('42');
        qt_layout_add_widget($rightCol, $seedEdit);

        qt_layout_add_layout($paramsRow, $rightCol);
        qt_layout_add_layout($mainLayout, $paramsRow);

        // === Generate Button ===
        $btnRow = qt_layout_hbox_create();
        qt_layout_add_stretch($btnRow);
        $this->generateBtn = qt_button_create('▶  Generate Video');
        qt_button_set_on_click($this->generateBtn, 'generate');
        qt_layout_add_widget($btnRow, $this->generateBtn);
        qt_layout_add_stretch($btnRow);
        qt_layout_add_layout($mainLayout, $btnRow);

        // === Progress Bar ===
        $this->progressBar = qt_progress_bar_create(0, 100);
        qt_progress_bar_set_text_visible($this->progressBar, true);
        qt_progress_bar_set_format($this->progressBar, '%v/%m (%p%)');
        qt_progress_bar_set_value($this->progressBar, 0);
        qt_layout_add_widget($mainLayout, $this->progressBar);

        // === Status Label ===
        $this->statusLabel = qt_label_create('Ready. Select a model directory to begin.');
        qt_label_set_word_wrap($this->statusLabel, true);
        qt_layout_add_widget($mainLayout, $this->statusLabel);

        // === Log Output ===
        $logLabel = qt_label_create('Log:');
        qt_layout_add_widget($mainLayout, $logLabel);

        $this->logEdit = qt_text_edit_create('');
        qt_text_edit_set_read_only($this->logEdit, true);
        qt_layout_add_widget($mainLayout, $this->logEdit);

        // Apply pre-populated model dir if set
        if ($this->modelDir) {
            qt_label_set_text($this->statusLabel, 'Model: ' . basename($this->modelDir));
        }

        // Set central widget
        qt_window_set_central_widget_handle($win, $mainWidget);

        // === Menu Bar ===
        $this->window->createMenuBar([
            ['title' => 'File', 'items' => [
                ['label' => 'Select Model Dir', 'action' => 'menu_browse'],
                ['label' => 'Exit', 'action' => 'menu_exit'],
            ]],
            ['title' => 'Language', 'items' => [
                ['label' => 'Change Language...', 'action' => 'menu_language'],
            ]],
            ['title' => 'Tools', 'items' => [
                ['label' => 'Environment Status', 'action' => 'menu_env'],
                ['label' => 'Model Manager', 'action' => 'menu_models'],
                ['label' => 'Model Recommendations', 'action' => 'menu_rec'],
                ['label' => 'Download Manager', 'action' => 'menu_download'],
                ['label' => 'Settings', 'action' => 'menu_settings'],
                ['label' => 'Node Editor', 'action' => 'menu_node_editor'],
                ['label' => 'Export Workflow JSON', 'action' => 'menu_export_json'],
            ]],
            ['title' => 'Help', 'items' => [
                ['label' => 'How to use', 'action' => 'menu_howto'],
                ['label' => 'About', 'action' => 'menu_about'],
            ]],
        ]);

        $this->window->show();
    }

    /**
     * Handle button click events.
     */
    public function onButtonClick(array $event): void
    {
        $callbackId = $event['callback_id'] ?? '';

        // Route recommendation dialog events
        if (null !== $this->recDialog && $this->recDialog->isVisible()) {
            if ($this->recDialog->handleEvent($event)) {
                return;
            }
        }

        switch ($callbackId) {
            case 'browse_model':
                $this->browseModelDir();
                break;

            case 'generate':
                $this->startGeneration();
                break;
        }
    }

    /**
     * Handle menu click events.
     */
    public function onMenuClick(array $event): void
    {
        $action = $event['action'] ?? '';

        switch ($action) {
            case 'menu_browse':
                $this->browseModelDir();
                break;

            case 'menu_exit':
                $this->qt->quit();
                break;

            case 'menu_about':
                $this->window->messageBox('About', 'H3PHP v' . H3PHP_VERSION . "\nMiniMax-H3 Video Generation Engine", 'info');
                break;

            case 'menu_language':
                $this->changeLanguage();
                break;

            case 'menu_env':
                $this->openEnvironment();
                break;

            case 'menu_models':
                $this->openModelManager();
                break;

            case 'menu_rec':
                $this->openRecommendations();
                break;

            case 'menu_download':
                $this->openDownload();
                break;

            case 'menu_settings':
                $this->openSettings();
                break;

            case 'menu_node_editor':
                $this->openNodeEditor();
                break;

            case 'menu_export_json':
                $this->exportWorkflowJson();
                break;

            case 'model_scan':
            case 'model_download_all':
            case 'model_validate_all':
                $this->routeModelDialogAction($event['action']);
                break;

            case 'download_start':
                if (null !== $this->downloadDialog && $this->downloadDialog->isVisible()) {
                    $this->downloadDialog->start();
                }
                break;

            case 'download_cancel':
                if (null !== $this->downloadManager) {
                    $this->downloadManager->cancelAll();
                }
                $this->closeDialog('download');
                break;

            case 'settings_save':
                if (null !== $this->settingsDialog && $this->settingsDialog->isVisible()) {
                    $this->settingsDialog->save();
                }
                break;

            case 'settings_cancel':
                $this->closeDialog('settings');
                break;

            case 'dialog_close':
                $this->closeDialog('model');
                break;

            case 'menu_howto':
                $this->showHowTo();
                break;
        }
    }

    /**
     * Forward a Model Manager dialog menu action to the open dialog.
     */
    private function routeModelDialogAction(string $action): void
    {
        if (null === $this->modelDialog || !$this->modelDialog->isVisible()) {
            return;
        }

        switch ($action) {
            case 'model_scan':
                $this->modelDialog->scanModels();
                break;
            case 'model_download_all':
                $this->modelDialog->downloadAll();
                break;
            case 'model_validate_all':
                $this->modelDialog->validateAll();
                break;
        }
    }

    /**
     * Open a language picker and apply the selected language.
     */
    private function changeLanguage(): void
    {
        if (null === $this->settings) {
            $this->settings = new SettingsManager();
            $this->settings->load();
        }

        $names = array_values(SetupWizard::LANGUAGES);
        $codes = array_keys(SetupWizard::LANGUAGES);
        $current = $this->settings->get('general.language', 'en');
        $curIdx = array_search($current, $codes, true);
        if (false === $curIdx) {
            $curIdx = 0;
        }

        $selected = qt_input_dialog_get_item(
            $this->window->getHandle(),
            'Language',
            'Select UI language:',
            $names,
            $curIdx,
            false
        );

        if ('' !== $selected) {
            $idx = array_search($selected, $names, true);
            $code = (false !== $idx) ? $codes[(int) $idx] : 'en';
            $this->settings->set('general.language', $code);
            $this->settings->save();
            qt_app_set_language($code);
        }
    }

    /**
     * Show the "How to use" help dialog.
     */
    private function showHowTo(): void
    {
        $text = "How to use H3PHP:\n\n"
            . "1. Select a model directory (File ▸ Select Model Dir)\n"
            . "2. Enter a prompt describing the video\n"
            . "3. Set parameters (Width, Height, Frames, Steps)\n"
            . "4. Click 'Generate Video' to start\n"
            . "5. Watch progress in the log area\n\n"
            . 'Tip: Change the UI language from Language ▸ Change Language.';
        $this->window->messageBox('How to use', $text, 'info');
    }

    /**
     * Handle text changed events.
     */
    public function onTextChanged(array $event): void
    {
        $callbackId = $event['callback_id'] ?? '';
        $text = $event['text'] ?? '';

        if ('model_dir' === $callbackId) {
            $this->modelDir = $text;
        }
    }

    /**
     * Handle combo box changed events.
     */
    public function onComboChanged(array $event): void
    {
        // Could update status label with current selection
    }

    /**
     * Open folder browser dialog for model directory.
     */
    private function browseModelDir(): void
    {
        $dir = $this->window->openFolderDialog('Select Model Directory');
        if ($dir) {
            $this->modelDir = $dir;
            // Update the line edit text
            // (Need to set text - we'll need to add this capability)
            $this->log("Model directory: {$dir}");
            $this->setStatus("Model selected: " . basename($dir));
        }
    }

    /**
     * Start video generation process.
     */
    private function startGeneration(): void
    {
        if ($this->generating) {
            $this->window->messageBox('Busy', 'Generation already in progress', 'warning');
            return;
        }

        if (!$this->modelDir || !is_dir($this->modelDir)) {
            $this->window->messageBox('Error', 'Please select a valid model directory first', 'error');
            return;
        }

        $prompt = qt_text_edit_get_text($this->promptEdit);
        if (!$prompt) {
            $this->window->messageBox('Error', 'Please enter a prompt', 'error');
            return;
        }

        $this->generating = true;
        qt_widget_set_enabled($this->generateBtn, false);
        $this->setStatus('Generating...');
        $this->log('Starting generation...');
        $this->log("  Model: {$this->modelDir}");
        $this->log("  Prompt: {$prompt}");
        $this->log("  Width: " . qt_combo_box_get_current_text($this->widthCombo));
        $this->log("  Height: " . qt_combo_box_get_current_text($this->heightCombo));
        $this->log("  Frames: " . qt_combo_box_get_current_text($this->framesCombo));
        $this->log("  Steps: " . qt_combo_box_get_current_text($this->stepsCombo));
        $this->log("  Output: " . qt_line_edit_get_text($this->outputEdit));

        // Simulate progress (real implementation would hook into pipeline events)
        $this->simulateGeneration();
    }

    /**
     * Simulate generation progress (placeholder for real pipeline integration).
     */
    private function simulateGeneration(): void
    {
        $steps = (int) qt_combo_box_get_current_text($this->stepsCombo);
        qt_progress_bar_set_value($this->progressBar, 0);

        // Post events to simulate progress - in real implementation,
        // this would be driven by pipeline callbacks
        for ($i = 1; $i <= $steps; $i++) {
            $percent = (int) round($i / $steps * 100);
            $this->qt->postEvent('generation_progress', [
                'step' => (string) $i,
                'total' => (string) $steps,
                'percent' => (string) $percent,
            ]);
        }
    }

    /**
     * Handle generation progress events.
     */
    public function onGenerationProgress(array $event): void
    {
        $percent = (int) ($event['percent'] ?? 0);
        $step = $event['step'] ?? '?';
        $total = $event['total'] ?? '?';

        qt_progress_bar_set_value($this->progressBar, $percent);

        if ($percent >= 100) {
            $this->generating = false;
            qt_widget_set_enabled($this->generateBtn, true);
            $this->setStatus('Generation complete!');
            $this->log('Done!');
        }
    }

    /**
     * Append a message to the log output.
     */
    private function log(string $message): void
    {
        qt_text_edit_append($this->logEdit, $message);
    }

    /**
     * Open (or refresh) the environment status panel.
     */
    private function openEnvironment(): void
    {
        if (null === $this->detector) {
            $this->detector = new EnvironmentDetector();
        }

        if (null === $this->envPanel) {
            $this->envPanel = new EnvironmentPanel($this->detector);
        }

        if ($this->envPanel->isVisible()) {
            $this->envPanel->refresh();
            return;
        }

        $this->envPanel->show();
    }

    /**
     * Open (or refresh) the model manager dialog.
     */
    private function openModelManager(): void
    {
        if (null === $this->modelManager) {
            $this->modelManager = new ModelManager();
        }
        if (null === $this->downloadManager) {
            $this->downloadManager = new DownloadManager();
        }
        if (null === $this->modelDialog) {
            $this->modelDialog = new ModelManagerDialog($this->modelManager, $this->downloadManager);
        }

        if ($this->modelDialog->isVisible()) {
            $this->modelDialog->scanModels();
            return;
        }

        $this->modelDialog->show();
    }

    /**
     * Open (or refresh) the model recommendation dialog.
     */
    private function openRecommendations(): void
    {
        if (null === $this->detector) {
            $this->detector = new EnvironmentDetector();
        }
        if (null === $this->downloadManager) {
            $this->downloadManager = new DownloadManager();
        }
        if (null === $this->recDialog) {
            $this->recDialog = new ModelRecommendationDialog($this->detector, $this->downloadManager);
        }

        if ($this->recDialog->isVisible()) {
            return;
        }

        $this->recDialog->show();
    }

    /**
     * Open (or refresh) the download progress dialog.
     */
    private function openDownload(): void
    {
        if (null === $this->downloadManager) {
            $this->downloadManager = new DownloadManager();
        }
        if (null === $this->downloadDialog) {
            $this->downloadDialog = new DownloadProgressDialog($this->downloadManager);
        }

        if ($this->downloadDialog->isVisible()) {
            $this->downloadDialog->update();
            return;
        }

        $this->downloadDialog->show();
    }

    /**
     * Open the settings dialog (reuses the loaded SettingsManager).
     */
    private function openSettings(): void
    {
        if (null === $this->settings) {
            $this->settings = new SettingsManager();
            $this->settings->load();
        }
        if (null === $this->settingsDialog) {
            $this->settingsDialog = new SettingsDialog($this->settings);
        }

        if ($this->settingsDialog->isVisible()) {
            return;
        }

        $this->settingsDialog->show();
    }

    /**
     * Close a managed dialog and drop the reference.
     */
    private function closeDialog(string $which): void
    {
        switch ($which) {
            case 'model':
                if (null !== $this->modelDialog && $this->modelDialog->isVisible()) {
                    $this->modelDialog->close();
                    $this->modelDialog = null;
                }
                break;
            case 'rec':
                if (null !== $this->recDialog && $this->recDialog->isVisible()) {
                    $this->recDialog->close();
                    $this->recDialog = null;
                }
                break;
            case 'download':
                if (null !== $this->downloadDialog && $this->downloadDialog->isVisible()) {
                    $this->downloadDialog->close();
                    $this->downloadDialog = null;
                }
                break;
            case 'settings':
                if (null !== $this->settingsDialog && $this->settingsDialog->isVisible()) {
                    $this->settingsDialog->close();
                    $this->settingsDialog = null;
                }
                break;
        }
    }

    /**
     * Open the node editor and load the default H3 workflow into the canvas.
     *
     * NOTE: NodeCanvas currently has no show()/attach-to-window API, so the
     * canvas is built in memory but cannot be displayed standalone yet. The
     * C++ layer (qt_node_canvas_create) needs a window-mount step to render.
     */
    private function openNodeEditor(): void
    {
        if (null === $this->workflowGraph) {
            $this->workflowGraph = H3NodeLibrary::getDefaultWorkflow();
        }
        if (null === $this->nodeCanvas) {
            $this->nodeCanvas = new NodeCanvas();
        }

        $this->nodeCanvas->clear();
        $this->nodeCanvas->setGridVisible(true);

        foreach ($this->workflowGraph->getNodes() as $node) {
            $this->nodeCanvas->addNode(
                $node->id,
                $node->title,
                $node->type,
                $node->x,
                $node->y,
                $node->inputs,
                $node->outputs,
            );
        }

        foreach ($this->workflowGraph->getConnections() as $conn) {
            $this->nodeCanvas->addConnection(
                $conn->id,
                $conn->sourceNodeId,
                $conn->sourcePort,
                $conn->targetNodeId,
                $conn->targetPort,
            );
        }

        $this->nodeCanvas->fitInView();
        $this->nodeCanvas->show();
    }

    /**
     * Serialize the default H3 workflow to ComfyUI JSON and show/save it.
     */
    private function exportWorkflowJson(): void
    {
        if (null === $this->workflowGraph) {
            $this->workflowGraph = H3NodeLibrary::getDefaultWorkflow();
        }
        if (null === $this->serializer) {
            $this->serializer = new ComfyUISerializer();
        }

        $workflow = $this->serializer->serialize($this->workflowGraph);
        $json = json_encode($workflow, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $outDir = 'output';
        if (!is_dir($outDir)) {
            @mkdir($outDir, 0777, true);
        }
        @file_put_contents($outDir . '/h3_default_workflow.json', $json);

        $this->window->messageBox('ComfyUI Workflow JSON', $json, 'info');
    }

    /**
     * Update the status label.
     */
    private function setStatus(string $status): void
    {
        qt_label_set_text($this->statusLabel, $status);
    }
}
