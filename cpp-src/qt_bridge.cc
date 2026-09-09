/**
 * H3PHP — Qt Bridge (C++17).
 *
 * Implements Qt GUI functions for PHP via phpx.h ABI.
 * Pattern from TypePHP ssh-tunnel-qt example:
 * - PHP owns all business logic
 * - Qt only handles UI + event loop
 * - Opaque int handles for C++ objects (GC-safe)
 * - Event queue: C++ enqueues, PHP polls and dispatches
 *
 * Cross-platform: macOS, Windows, Linux (Qt 6).
 */

#include <phpx.h>
#include <QtWidgets/QtWidgets>
#include <QtCore/QtCore>
#include <QtGui/QtGui>
#include <queue>
#include <mutex>
#include <string>
#include <unordered_map>
#include <functional>

using namespace php;

// ============================================================================
// Opaque handle storage
// ============================================================================
typedef int64_t QtHandle;

static std::mutex g_qt_mutex;
static std::unordered_map<QtHandle, QWidget*> g_widgets;
static std::unordered_map<QtHandle, QAction*> g_actions;
static std::atomic<QtHandle> g_nextHandle{1};

// Event queue for PHP polling
struct QtEvent {
    std::string type;
    std::unordered_map<std::string, std::string> data;
};

static std::queue<QtEvent> g_eventQueue;
static std::mutex g_eventMutex;

// QApplication singleton
static QApplication* g_app = nullptr;
static QTranslator* g_translator = nullptr;
static int g_argc = 1;
static char g_appName[] = "h3php";
static char* g_argv[] = {g_appName, nullptr};

static QtHandle allocHandle() { return g_nextHandle++; }

static void storeWidget(QtHandle h, QWidget* w) {
    std::lock_guard<std::mutex> lock(g_qt_mutex);
    g_widgets[h] = w;
}

static QWidget* getWidget(QtHandle h) {
    std::lock_guard<std::mutex> lock(g_qt_mutex);
    auto it = g_widgets.find(h);
    return it != g_widgets.end() ? it->second : nullptr;
}

static void removeWidget(QtHandle h) {
    std::lock_guard<std::mutex> lock(g_qt_mutex);
    auto it = g_widgets.find(h);
    if (it != g_widgets.end()) {
        // Qt parent-child handles deletion
        g_widgets.erase(it);
    }
}

static void enqueueEvent(const std::string& type, const std::unordered_map<std::string, std::string>& data) {
    std::lock_guard<std::mutex> lock(g_eventMutex);
    g_eventQueue.push({type, data});
}

// ============================================================================
// Stub implementations
// ============================================================================

int64_t php_qt_app_init() {
    if (g_app) return 0;

#ifdef __APPLE__
    @autoreleasepool {
#endif
        g_app = new QApplication(g_argc, g_argv);
#ifdef __APPLE__
    }
#endif
    return 0;
}

void php_qt_app_set_language(String code) {
    if (!g_app) return;

    std::string base = code.toStdString();
    QString qm = QString::fromStdString(base);

    // Candidate paths for the Qt framework translation file.
    QStringList candidates;
    candidates << (QCoreApplication::applicationDirPath() + "/translations/qt_" + qm + ".qm");
    candidates << ("translations/qt_" + qm + ".qm");
    candidates << ("qt_" + qm + ".qm");

    QString found;
    for (auto&& c : candidates) {
        if (QFile::exists(c)) {
            found = c;
            break;
        }
    }
    if (found.isEmpty()) {
        return; // Requested language not available
    }

    if (g_translator) {
        QCoreApplication::removeTranslator(g_translator);
        delete g_translator;
        g_translator = nullptr;
    }

    g_translator = new QTranslator();
    if (g_translator->load(found)) {
        QCoreApplication::installTranslator(g_translator);
    } else {
        delete g_translator;
        g_translator = nullptr;
    }
}

int64_t php_qt_app_exec() {
    if (!g_app) return -1;
    return g_app->exec();
}

bool php_qt_app_process_events() {
    if (!g_app) return false;
    g_app->processEvents();
    return true;
}

void php_qt_app_quit() {
    if (g_app) g_app->quit();
}

int64_t php_qt_window_create() {
#ifdef __APPLE__
    @autoreleasepool {
#endif
        QMainWindow* win = new QMainWindow();
        win->setWindowTitle("H3PHP");
        QtHandle h = allocHandle();
        storeWidget(h, win);
#ifdef __APPLE__
    }
#endif
    return (int64_t)h;
}

void php_qt_window_set_title(int64_t window, String title) {
    QMainWindow* win = qobject_cast<QMainWindow*>(getWidget(window));
    if (win) win->setWindowTitle(QString::fromStdString(title.toStdString()));
}

void php_qt_window_set_size(int64_t window, int64_t width, int64_t height) {
    QMainWindow* win = qobject_cast<QMainWindow*>(getWidget(window));
    if (win) win->resize(width, height);
}

void php_qt_window_show(int64_t window) {
    QWidget* w = getWidget(window);
    if (w) w->show();
}

bool php_qt_window_is_visible(int64_t window) {
    QWidget* w = getWidget(window);
    return w && w->isVisible();
}

int64_t php_qt_window_set_central_widget(int64_t window, String widgetType, String text) {
    QMainWindow* win = qobject_cast<QMainWindow*>(getWidget(window));
    if (!win) return 0;

    QWidget* widget = nullptr;
    QString t = QString::fromStdString(text.toStdString());

    if (widgetType.toStdString() == "QLabel") {
        widget = new QLabel(t);
    } else if (widgetType.toStdString() == "QTextEdit") {
        QTextEdit* te = new QTextEdit();
        te->setPlainText(t);
        widget = te;
    } else {
        widget = new QWidget();
    }

    win->setCentralWidget(widget);
    QtHandle h = allocHandle();
    storeWidget(h, widget);
    return (int64_t)h;
}

int64_t php_qt_window_create_menu_bar(int64_t window, Array menus) {
    QMainWindow* win = qobject_cast<QMainWindow*>(getWidget(window));
    if (!win) return 0;

    QMenuBar* menuBar = win->menuBar();

    for (auto&& entry : menus) {
        Array menuDef = entry.value;
        std::string title = menuDef["title"].toStdString();
        QMenu* menu = menuBar->addMenu(QString::fromStdString(title));

        Array items = menuDef["items"];
        for (auto&& itemEntry : items) {
            Array item = itemEntry.value;
            std::string label = item["label"].toStdString();
            std::string action = item["action"].toStdString();

            QAction* qaction = menu->addAction(QString::fromStdString(label));

            QObject::connect(qaction, &QAction::triggered, [action]() {
                enqueueEvent("menu_click", {{"action", action}});
            });

            // QAction is parented to menu — no separate handle needed
            // (QAction inherits QObject, not QWidget — storing as QWidget* is incorrect)
        }
    }

    QtHandle h = allocHandle();
    storeWidget(h, menuBar);
    return (int64_t)h;
}

// Event polling — returns array or null
Variant php_qt_app_poll_event() {
    std::lock_guard<std::mutex> lock(g_eventMutex);
    if (g_eventQueue.empty()) {
        return Variant(nullptr);
    }

    QtEvent evt = g_eventQueue.front();
    g_eventQueue.pop();

    Array result;
    result["type"] = evt.type;
    for (auto& kv : evt.data) {
        result[kv.first] = kv.second;
    }
    return result;
}

void php_qt_app_post_event(String eventType, Array data) {
    std::unordered_map<std::string, std::string> eventData;
    for (auto&& kv : data) {
        eventData[kv.key.toStdString()] = kv.value.toString().toStdString();
    }
    enqueueEvent(eventType.toStdString(), eventData);
}

int64_t php_qt_label_create(String text) {
    QLabel* label = new QLabel(QString::fromStdString(text.toStdString()));
    QtHandle h = allocHandle();
    storeWidget(h, label);
    return (int64_t)h;
}

void php_qt_label_set_text(int64_t label, String text) {
    QLabel* l = qobject_cast<QLabel*>(getWidget(label));
    if (l) l->setText(QString::fromStdString(text.toStdString()));
}

int64_t php_qt_button_create(String text) {
    QPushButton* btn = new QPushButton(QString::fromStdString(text.toStdString()));
    QtHandle h = allocHandle();
    storeWidget(h, btn);
    return (int64_t)h;
}

int64_t php_qt_line_edit_create(String placeholder) {
    QLineEdit* edit = new QLineEdit();
    edit->setPlaceholderText(QString::fromStdString(placeholder.toStdString()));
    QtHandle h = allocHandle();
    storeWidget(h, edit);
    return (int64_t)h;
}

String php_qt_line_edit_get_text(int64_t edit) {
    QLineEdit* e = qobject_cast<QLineEdit*>(getWidget(edit));
    return e ? e->text().toStdString() : "";
}

int64_t php_qt_combo_box_create(Array items) {
    QComboBox* combo = new QComboBox();
    for (auto&& item : items) {
        combo->addItem(QString::fromStdString(item.value.toString().toStdString()));
    }
    QtHandle h = allocHandle();
    storeWidget(h, combo);
    return (int64_t)h;
}

int64_t php_qt_progress_bar_create(int64_t min, int64_t max) {
    QProgressBar* bar = new QProgressBar();
    bar->setRange(min, max);
    QtHandle h = allocHandle();
    storeWidget(h, bar);
    return (int64_t)h;
}

void php_qt_progress_bar_set_value(int64_t progress, int64_t value) {
    QProgressBar* bar = qobject_cast<QProgressBar*>(getWidget(progress));
    if (bar) bar->setValue(value);
}

int64_t php_qt_text_edit_create(String text) {
    QTextEdit* te = new QTextEdit();
    te->setPlainText(QString::fromStdString(text.toStdString()));
    QtHandle h = allocHandle();
    storeWidget(h, te);
    return (int64_t)h;
}

String php_qt_text_edit_get_text(int64_t edit) {
    QTextEdit* te = qobject_cast<QTextEdit*>(getWidget(edit));
    return te ? te->toPlainText().toStdString() : "";
}

int64_t php_qt_splitter_create(String orientation) {
    QSplitter* splitter = new QSplitter();
    if (orientation.toStdString() == "vertical") {
        splitter->setOrientation(Qt::Vertical);
    } else {
        splitter->setOrientation(Qt::Horizontal);
    }
    QtHandle h = allocHandle();
    storeWidget(h, splitter);
    return (int64_t)h;
}

void php_qt_message_box_show(int64_t window, String title, String message, String icon) {
    QWidget* parent = getWidget(window);
    QMessageBox::Icon qicon = QMessageBox::Information;
    if (icon.toStdString() == "warning") qicon = QMessageBox::Warning;
    else if (icon.toStdString() == "error") qicon = QMessageBox::Critical;
    else if (icon.toStdString() == "question") qicon = QMessageBox::Question;

    QMessageBox::information(parent, QString::fromStdString(title.toStdString()),
                             QString::fromStdString(message.toStdString()));
}

String php_qt_file_dialog_open(int64_t window, String title, String filter) {
    QWidget* parent = getWidget(window);
    QString result = QFileDialog::getOpenFileName(
        parent,
        QString::fromStdString(title.toStdString()),
        "",
        QString::fromStdString(filter.toStdString())
    );
    return result.toStdString();
}

String php_qt_folder_dialog_open(int64_t window, String title) {
    QWidget* parent = getWidget(window);
    QString result = QFileDialog::getExistingDirectory(
        parent,
        QString::fromStdString(title.toStdString())
    );
    return result.toStdString();
}

void php_qt_destroy(int64_t handle) {
    removeWidget(handle);
    // Qt parent-child handles actual deletion
}

// ============================================================================
// Layout management
// ============================================================================

int64_t php_qt_layout_vbox_create() {
    QVBoxLayout* layout = new QVBoxLayout();
    QtHandle h = allocHandle();
    // Store as QWidget* for type compatibility (we only need the handle)
    storeWidget(h, reinterpret_cast<QWidget*>(layout));
    return (int64_t)h;
}

int64_t php_qt_layout_hbox_create() {
    QHBoxLayout* layout = new QHBoxLayout();
    QtHandle h = allocHandle();
    storeWidget(h, reinterpret_cast<QWidget*>(layout));
    return (int64_t)h;
}

void php_qt_layout_add_layout(int64_t parent, int64_t child) {
    QBoxLayout* p = reinterpret_cast<QBoxLayout*>(getWidget(parent));
    QLayout* c = reinterpret_cast<QLayout*>(getWidget(child));
    if (p && c) {
        p->addLayout(c);
    }
}

void php_qt_widget_set_layout(int64_t widget, int64_t layout) {
    QWidget* w = getWidget(widget);
    QLayout* l = reinterpret_cast<QLayout*>(getWidget(layout));
    if (w && l) {
        w->setLayout(l);
    }
}

void php_qt_layout_add_widget(int64_t layout, int64_t widget) {
    QLayout* l = reinterpret_cast<QLayout*>(getWidget(layout));
    QWidget* w = getWidget(widget);
    if (l && w) {
        l->addWidget(w);
    }
}

void php_qt_layout_set_spacing(int64_t layout, int64_t spacing) {
    QLayout* l = reinterpret_cast<QLayout*>(getWidget(layout));
    if (l) l->setSpacing(spacing);
}

void php_qt_layout_set_margins(int64_t layout, int64_t left, int64_t top, int64_t right, int64_t bottom) {
    QLayout* l = reinterpret_cast<QLayout*>(getWidget(layout));
    if (l) l->setContentsMargins(left, top, right, bottom);
}

void php_qt_layout_add_stretch(int64_t layout) {
    QBoxLayout* l = reinterpret_cast<QBoxLayout*>(getWidget(layout));
    if (l) l->addStretch();
}

void php_qt_layout_set_alignment(int64_t layout, int64_t widget, String alignment) {
    QLayout* l = reinterpret_cast<QLayout*>(getWidget(layout));
    QWidget* w = getWidget(widget);
    if (!l || !w) return;
    Qt::Alignment align = Qt::AlignLeft;
    std::string a = alignment.toStdString();
    if (a == "center") align = Qt::AlignCenter;
    else if (a == "right") align = Qt::AlignRight;
    else if (a == "top") align = Qt::AlignTop;
    else if (a == "bottom") align = Qt::AlignBottom;
    l->setAlignment(w, align);
}

// ============================================================================
// Additional widget helpers
// ============================================================================

void php_qt_button_set_on_click(int64_t button, String callback_id) {
    QPushButton* btn = qobject_cast<QPushButton*>(getWidget(button));
    if (btn) {
        QObject::connect(btn, &QPushButton::clicked, [callback_id]() {
            enqueueEvent("button_click", {{"callback_id", callback_id.toStdString()}});
        });
    }
}

void php_qt_line_edit_set_on_text_changed(int64_t edit, String callback_id) {
    QLineEdit* e = qobject_cast<QLineEdit*>(getWidget(edit));
    if (e) {
        QObject::connect(e, &QLineEdit::textChanged, [callback_id, e]() {
            enqueueEvent("text_changed", {
                {"callback_id", callback_id.toStdString()},
                {"text", e->text().toStdString()}
            });
        });
    }
}

void php_qt_combo_box_set_on_current_index_changed(int64_t combo, String callback_id) {
    QComboBox* cb = qobject_cast<QComboBox*>(getWidget(combo));
    if (cb) {
        QObject::connect(cb, QOverload<int>::of(&QComboBox::currentIndexChanged), [callback_id, cb]() {
            enqueueEvent("combo_changed", {
                {"callback_id", callback_id.toStdString()},
                {"index", std::to_string(cb->currentIndex())},
                {"text", cb->currentText().toStdString()}
            });
        });
    }
}

String php_qt_combo_box_get_current_text(int64_t combo) {
    QComboBox* cb = qobject_cast<QComboBox*>(getWidget(combo));
    return cb ? cb->currentText().toStdString() : "";
}

int64_t php_qt_combo_box_get_current_index(int64_t combo) {
    QComboBox* cb = qobject_cast<QComboBox*>(getWidget(combo));
    return cb ? cb->currentIndex() : -1;
}

void php_qt_combo_box_set_current_index(int64_t combo, int64_t index) {
    QComboBox* cb = qobject_cast<QComboBox*>(getWidget(combo));
    if (cb) cb->setCurrentIndex(index);
}

void php_qt_progress_bar_set_text_visible(int64_t progress, bool visible) {
    QProgressBar* bar = qobject_cast<QProgressBar*>(getWidget(progress));
    if (bar) bar->setTextVisible(visible);
}

void php_qt_progress_bar_set_format(int64_t progress, String format) {
    QProgressBar* bar = qobject_cast<QProgressBar*>(getWidget(progress));
    if (bar) bar->setFormat(QString::fromStdString(format.toStdString()));
}

void php_qt_label_set_word_wrap(int64_t label, bool wrap) {
    QLabel* l = qobject_cast<QLabel*>(getWidget(label));
    if (l) l->setWordWrap(wrap);
}

void php_qt_text_edit_set_read_only(int64_t edit, bool read_only) {
    QTextEdit* te = qobject_cast<QTextEdit*>(getWidget(edit));
    if (te) te->setReadOnly(read_only);
}

void php_qt_text_edit_append(int64_t edit, String text) {
    QTextEdit* te = qobject_cast<QTextEdit*>(getWidget(edit));
    if (te) te->append(QString::fromStdString(text.toStdString()));
}

void php_qt_window_set_central_widget_handle(int64_t window, int64_t widget) {
    QMainWindow* win = qobject_cast<QMainWindow*>(getWidget(window));
    QWidget* w = getWidget(widget);
    if (win && w) {
        win->setCentralWidget(w);
    }
}

int64_t php_qt_widget_create() {
    QWidget* w = new QWidget();
    QtHandle h = allocHandle();
    storeWidget(h, w);
    return (int64_t)h;
}

void php_qt_widget_set_enabled(int64_t widget, bool enabled) {
    QWidget* w = getWidget(widget);
    if (w) w->setEnabled(enabled);
}

String php_qt_input_dialog_get_text(int64_t window, String title, String label, String default_text) {
    QWidget* parent = getWidget(window);
    bool ok;
    QString text = QInputDialog::getText(
        parent,
        QString::fromStdString(title.toStdString()),
        QString::fromStdString(label.toStdString()),
        QLineEdit::Normal,
        QString::fromStdString(default_text.toStdString()),
        &ok
    );
    return ok ? text.toStdString() : "";
}

String php_qt_input_dialog_get_item(int64_t window, String title, String label, Array items, int64_t current, bool editable) {
    QWidget* parent = getWidget(window);
    QStringList qitems;
    for (auto&& item : items) {
        qitems << QString::fromStdString(item.value.toString().toStdString());
    }
    bool ok;
    QString text = QInputDialog::getItem(
        parent,
        QString::fromStdString(title.toStdString()),
        QString::fromStdString(label.toStdString()),
        qitems,
        current,
        editable,
        &ok
    );
    return ok ? text.toStdString() : "";
}

int64_t php_qt_input_dialog_get_int(int64_t window, String title, String label, int64_t value, int64_t min, int64_t max, int64_t step) {
    QWidget* parent = getWidget(window);
    bool ok;
    int result = QInputDialog::getInt(
        parent,
        QString::fromStdString(title.toStdString()),
        QString::fromStdString(label.toStdString()),
        value, min, max, step, &ok
    );
    return ok ? result : -1;
}

int64_t php_qt_input_dialog_get_double(int64_t window, String title, String label, int64_t value, int64_t min, int64_t max, int64_t decimals) {
    QWidget* parent = getWidget(window);
    bool ok;
    double result = QInputDialog::getDouble(
        parent,
        QString::fromStdString(title.toStdString()),
        QString::fromStdString(label.toStdString()),
        (double)value, (double)min, (double)max, (int)decimals, &ok
    );
    return ok ? (int64_t)result : -1;
}
