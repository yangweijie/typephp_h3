<?php

/**
 * H3PHP — Qt Application Lifecycle.
 *
 * Manages the QApplication singleton and event loop.
 * Follows the pattern from TypePHP ssh-tunnel-qt example:
 * - PHP owns all business logic
 * - Qt only handles UI + event loop
 * - Event queue: C++ enqueues, PHP polls and dispatches
 */

namespace H3Php\Qt;

class Application
{
    /** Whether the Qt app has been initialized */
    private bool $initialized = false;

    /** Whether the event loop is running */
    private bool $running = false;

    /** Event handler callbacks */
    private array $handlers = [];

    /** Per-frame tick handlers (event-loop polling) */
    private array $tickHandlers = [];

    /**
     * Initialize the Qt application.
     * Must be called before creating any windows.
     */
    public function init(): bool
    {
        if ($this->initialized) {
            return true;
        }

        $result = qt_app_init();
        $this->initialized = (0 === $result);

        return $this->initialized;
    }

    /**
     * Run the Qt event loop.
     * Blocks until quit() is called.
     * Processes events and dispatches to registered handlers.
     */
    public function run(): int
    {
        if (!$this->initialized) {
            if (!$this->init()) {
                return 1;
            }
        }

        $this->running = true;

        // Event loop: poll events and dispatch
        while ($this->running) {
            $this->pump();
            $this->tick();
            usleep(16000); // ~60fps
        }

        return 0;
    }

    /**
     * Process pending Qt events and dispatch queued events once.
     * Used both by run() and by modal flows (e.g. setup wizard) that need
     * to drive the event loop without entering the full run() loop.
     */
    public function pump(): void
    {
        qt_app_process_events();

        while (true) {
            $event = qt_app_poll_event();
            if (null === $event) {
                break;
            }
            $this->dispatch($event);
        }
    }

    /**
     * Run the native Qt event loop (blocking).
     * Use this for simple apps that don't need PHP-side event handling.
     */
    public function exec(): int
    {
        if (!$this->initialized) {
            if (!$this->init()) {
                return 1;
            }
        }

        return qt_app_exec();
    }

    /**
     * Quit the application.
     */
    public function quit(): void
    {
        $this->running = false;
        qt_app_quit();
    }

    /**
     * Register an event handler.
     *
     * @param string $eventType Event type (e.g., 'menu_click', 'button_click')
     * @param callable $handler Function(array $event): void
     */
    public function on(string $eventType, callable $handler): void
    {
        if (!isset($this->handlers[$eventType])) {
            $this->handlers[$eventType] = [];
        }
        $this->handlers[$eventType][] = $handler;
    }

    /**
     * Post a custom event to the event queue.
     *
     * @param string $eventType Event type identifier
     * @param array $data Event data
     */
    public function postEvent(string $eventType, array $data = []): void
    {
        qt_app_post_event($eventType, $data);
    }

    /**
     * Check if the application is initialized.
     */
    public function isInitialized(): bool
    {
        return $this->initialized;
    }

    /**
     * Check if the event loop is running.
     */
    public function isRunning(): bool
    {
        return $this->running;
    }

    /**
     * Register a per-frame tick handler.
     * Called once per event-loop iteration (after pump). Use for polling
     * state that must refresh continuously (e.g. download progress).
     */
    public function onTick(callable $handler): void
    {
        $this->tickHandlers[] = $handler;
    }

    /**
     * Invoke all registered tick handlers once.
     */
    private function tick(): void
    {
        foreach ($this->tickHandlers as $handler) {
            try {
                $handler();
            } catch (\Throwable $e) {
                // Log error but don't crash the event loop
                fprintf(STDERR, "Tick handler error: {$e->getMessage()}\n");
            }
        }
    }

    /**
     * Dispatch an event to registered handlers.
     */
    private function dispatch(array $event): void
    {
        $type = $event['type'] ?? 'unknown';
        $handlers = $this->handlers[$type] ?? [];

        foreach ($handlers as $handler) {
            try {
                $handler($event);
            } catch (\Throwable $e) {
                // Log error but don't crash the event loop
                fprintf(STDERR, "Event handler error [{$type}]: {$e->getMessage()}\n");
            }
        }
    }
}
