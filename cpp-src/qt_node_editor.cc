/**
 * H3PHP — Qt Node Editor (C++17).
 *
 * QGraphicsView-based node editor for ComfyUI workflow editing.
 * Implements the node graph canvas with:
 * - Draggable node items
 * - Bezier curve connections
 * - Grid background with snap-to-grid
 * - Zoom and pan
 * - Event queue for PHP polling
 */

#include <phpx.h>
#include <QtWidgets/QtWidgets>
#include <QtCore/QtCore>
#include <QtGui/QtGui>
#include <queue>
#include <mutex>
#include <string>
#include <unordered_map>
#include <vector>
#include <cmath>

using namespace php;

// ============================================================================
// Data structures
// ============================================================================
typedef int64_t NodeHandle;

static std::atomic<NodeHandle> g_nextNodeHandle{1};
static std::mutex g_nodeMutex;

struct PortInfo {
    std::string name;
    std::string type;
};

struct NodeInfo {
    std::string id;
    std::string title;
    std::string type;
    int x, y;
    std::vector<PortInfo> inputs;
    std::vector<PortInfo> outputs;
    QGraphicsItem* item;
    bool selected;
};

struct ConnectionInfo {
    std::string id;
    std::string sourceNode;
    std::string sourcePort;
    std::string targetNode;
    std::string targetPort;
    QGraphicsPathItem* item;
};

struct CanvasInfo {
    QGraphicsView* view;
    QGraphicsScene* scene;
    QWidget* window = nullptr;
    std::unordered_map<std::string, NodeInfo> nodes;
    std::unordered_map<std::string, ConnectionInfo> connections;
    bool gridVisible;
    bool snapEnabled;
    int gridSize;
    float zoom;
};

static std::unordered_map<NodeHandle, CanvasInfo> g_canvases;
static std::queue<QString> g_eventQueue;
static std::mutex g_eventMutex;

static NodeHandle allocHandle() { return g_nextNodeHandle++; }

// ============================================================================
// Custom QGraphicsItem for nodes
// ============================================================================
class NodeGraphicsItem : public QGraphicsItem
{
public:
    NodeInfo* info;
    static const int WIDTH = 180;
    static const int HEADER_HEIGHT = 30;
    static const int PORT_HEIGHT = 20;
    static const int HEIGHT = HEADER_HEIGHT + 50;

    NodeGraphicsItem(NodeInfo* info) : info(info) {
        setFlag(QGraphicsItem::ItemIsMovable);
        setFlag(QGraphicsItem::ItemIsSelectable);
        setFlag(QGraphicsItem::ItemSendsGeometryChanges);
    }

    QRectF boundingRect() const override {
        int maxPorts = std::max(info->inputs.size(), info->outputs.size());
        int h = HEADER_HEIGHT + maxPorts * PORT_HEIGHT + 10;
        return QRectF(0, 0, WIDTH, h);
    }

    void paint(QPainter* painter, const QStyleOptionGraphicsItem*, QWidget*) override {
        int maxPorts = std::max(info->inputs.size(), info->outputs.size());
        int h = HEADER_HEIGHT + maxPorts * PORT_HEIGHT + 10;

        // Background
        painter->setBrush(QColor(60, 60, 60, 220));
        painter->setPen(QPen(Qt::gray, 1));
        painter->drawRoundedRect(QRectF(0, 0, WIDTH, h), 5, 5);

        // Header
        painter->setBrush(QColor(80, 120, 200));
        painter->setPen(Qt::NoPen);
        painter->drawRoundedRect(QRectF(0, 0, WIDTH, HEADER_HEIGHT), 5, 5);
        painter->drawRect(QRectF(0, HEADER_HEIGHT - 5, WIDTH, 5));

        // Title
        painter->setPen(Qt::white);
        painter->setFont(QFont("Arial", 9, QFont::Bold));
        painter->drawText(QRectF(5, 0, WIDTH - 10, HEADER_HEIGHT),
                         Qt::AlignCenter, QString::fromStdString(info->title));

        // Ports
        painter->setFont(QFont("Arial", 8));
        for (size_t i = 0; i < info->inputs.size(); i++) {
            int py = HEADER_HEIGHT + i * PORT_HEIGHT;
            painter->setBrush(QColor(100, 200, 100));
            painter->drawEllipse(QPointF(8, py + PORT_HEIGHT / 2), 5, 5);
            painter->setPen(Qt::white);
            painter->drawText(QRectF(18, py, WIDTH / 2, PORT_HEIGHT),
                             Qt::AlignVCenter, QString::fromStdString(info->inputs[i].name));
        }
        for (size_t i = 0; i < info->outputs.size(); i++) {
            int py = HEADER_HEIGHT + i * PORT_HEIGHT;
            painter->setBrush(QColor(200, 100, 100));
            painter->drawEllipse(QPointF(WIDTH - 8, py + PORT_HEIGHT / 2), 5, 5);
            painter->setPen(Qt::white);
            painter->drawText(QRectF(WIDTH / 2, py, WIDTH / 2 - 18, PORT_HEIGHT),
                             Qt::AlignRight | Qt::AlignVCenter,
                             QString::fromStdString(info->outputs[i].name));
        }
    }

    QVariant itemChange(GraphicsItemChange change, const QVariant& value) override {
        if (change == QGraphicsItem::ItemPositionChange && scene()) {
            QPointF newPos = value.toPointF();
            // Snap to grid
            CanvasInfo* canvas = nullptr;
            for (auto& pair : g_canvases) {
                if (pair.second.scene == scene()) {
                    canvas = &pair.second;
                    break;
                }
            }
            if (canvas && canvas->snapEnabled) {
                int gs = canvas->gridSize;
                newPos.setX(std::round(newPos.x() / gs) * gs);
                newPos.setY(std::round(newPos.y() / gs) * gs);
            }
            return newPos;
        }
        if (change == QGraphicsItem::ItemPositionHasChanged) {
            // Emit event
            std::lock_guard<std::mutex> lock(g_eventMutex);
            QString event = QString("node_moved:%1:%2:%3")
                .arg(QString::fromStdString(info->id))
                .arg(info->x)
                .arg(info->y);
            g_eventQueue.push(event);
        }
        return QGraphicsItem::itemChange(change, value);
    }
};

// ============================================================================
// Stub implementations
// ============================================================================

int64_t php_qt_node_canvas_create() {
    CanvasInfo canvas;
    canvas.scene = new QGraphicsScene();
    canvas.view = new QGraphicsView(canvas.scene);
    canvas.view->setRenderHint(QPainter::Antialiasing);
    canvas.view->setDragMode(QGraphicsView::RubberBandDrag);
    canvas.gridVisible = true;
    canvas.snapEnabled = true;
    canvas.gridSize = 20;
    canvas.zoom = 1.0f;

    // Dark background
    canvas.view->setBackgroundBrush(QColor(40, 40, 40));

    NodeHandle h = allocHandle();
    std::lock_guard<std::mutex> lock(g_nodeMutex);
    g_canvases[h] = canvas;
    return (int64_t)h;
}

void php_qt_node_canvas_set_size(int64_t canvas, int64_t width, int64_t height) {
    std::lock_guard<std::mutex> lock(g_nodeMutex);
    auto it = g_canvases.find(canvas);
    if (it != g_canvases.end()) {
        it->second.view->setFixedSize(width, height);
        it->second.scene->setSceneRect(0, 0, width, height);
    }
}

int64_t php_qt_node_canvas_add_node(int64_t canvas,
    String nodeId,
    String title,
    String nodeType,
    int64_t x,
    int64_t y,
    Array inputs,
    Array outputs
) {
    std::lock_guard<std::mutex> lock(g_nodeMutex);
    auto it = g_canvases.find(canvas);
    if (it == g_canvases.end()) return 0;

    CanvasInfo& c = it->second;
    NodeInfo info;
    info.id = nodeId.toStdString();
    info.title = title.toStdString();
    info.type = nodeType.toStdString();
    info.x = x;
    info.y = y;
    info.selected = false;

    // Parse inputs
    for (auto&& entry : inputs) {
        Array portDef = entry.value;
        PortInfo p;
        p.name = portDef["name"].toStdString();
        p.type = portDef["type"].toStdString();
        info.inputs.push_back(p);
    }

    // Parse outputs
    for (auto&& entry : outputs) {
        Array portDef = entry.value;
        PortInfo p;
        p.name = portDef["name"].toStdString();
        p.type = portDef["type"].toStdString();
        info.outputs.push_back(p);
    }

    NodeGraphicsItem* item = new NodeGraphicsItem(&info);
    item->setPos(x, y);
    c.scene->addItem(item);
    info.item = item;

    c.nodes[info.id] = info;
    return (int64_t)allocHandle();
}

void php_qt_node_canvas_remove_node(int64_t canvas, String nodeId) {
    std::lock_guard<std::mutex> lock(g_nodeMutex);
    auto it = g_canvases.find(canvas);
    if (it == g_canvases.end()) return;

    auto nit = it->second.nodes.find(nodeId.toStdString());
    if (nit != it->second.nodes.end()) {
        it->second.scene->removeItem(nit->second.item);
        delete nit->second.item;
        it->second.nodes.erase(nit);
    }
}

int64_t php_qt_node_canvas_add_connection(int64_t canvas,
    String connectionId,
    String sourceNodeId,
    String sourcePort,
    String targetNodeId,
    String targetPort
) {
    std::lock_guard<std::mutex> lock(g_nodeMutex);
    auto it = g_canvases.find(canvas);
    if (it == g_canvases.end()) return 0;

    CanvasInfo& c = it->second;
    ConnectionInfo conn;
    conn.id = connectionId.toStdString();
    conn.sourceNode = sourceNodeId.toStdString();
    conn.sourcePort = sourcePort.toStdString();
    conn.targetNode = targetNodeId.toStdString();
    conn.targetPort = targetPort.toStdString();

    // Create bezier path
    QPainterPath path;
    // Simple line for now (bezier would need port positions)
    auto sit = c.nodes.find(conn.sourceNode);
    auto tit = c.nodes.find(conn.targetNode);
    if (sit != c.nodes.end() && tit != c.nodes.end()) {
        QPointF sp = sit->second.item->pos() + QPointF(180, 30);
        QPointF tp = tit->second.item->pos();
        path.moveTo(sp);
        path.cubicTo(sp + QPointF(50, 0), tp - QPointF(50, 0), tp);
    }

    conn.item = new QGraphicsPathItem(path);
    conn.item->setPen(QPen(QColor(200, 200, 100), 2));
    c.scene->addItem(conn.item);

    c.connections[conn.id] = conn;
    return (int64_t)allocHandle();
}

void php_qt_node_canvas_remove_connection(int64_t canvas, String connectionId) {
    std::lock_guard<std::mutex> lock(g_nodeMutex);
    auto it = g_canvases.find(canvas);
    if (it == g_canvases.end()) return;

    auto cit = it->second.connections.find(connectionId.toStdString());
    if (cit != it->second.connections.end()) {
        it->second.scene->removeItem(cit->second.item);
        delete cit->second.item;
        it->second.connections.erase(cit);
    }
}

void php_qt_node_canvas_move_node(int64_t canvas, String nodeId, int64_t x, int64_t y) {
    std::lock_guard<std::mutex> lock(g_nodeMutex);
    auto it = g_canvases.find(canvas);
    if (it == g_canvases.end()) return;

    auto nit = it->second.nodes.find(nodeId.toStdString());
    if (nit != it->second.nodes.end()) {
        nit->second.item->setPos(x, y);
        nit->second.x = x;
        nit->second.y = y;
    }
}

Array php_qt_node_canvas_get_node_position(int64_t canvas, String nodeId) {
    std::lock_guard<std::mutex> lock(g_nodeMutex);
    auto it = g_canvases.find(canvas);
    Array result;
    if (it == g_canvases.end()) return result;

    auto nit = it->second.nodes.find(nodeId.toStdString());
    if (nit != it->second.nodes.end()) {
        result["x"] = nit->second.x;
        result["y"] = nit->second.y;
    }
    return result;
}

Array php_qt_node_canvas_get_nodes(int64_t canvas) {
    std::lock_guard<std::mutex> lock(g_nodeMutex);
    auto it = g_canvases.find(canvas);
    Array result;
    if (it == g_canvases.end()) return result;

    for (auto& pair : it->second.nodes) {
        Array nodeInfo;
        nodeInfo["id"] = pair.second.id;
        nodeInfo["title"] = pair.second.title;
        nodeInfo["type"] = pair.second.type;
        nodeInfo["x"] = pair.second.x;
        nodeInfo["y"] = pair.second.y;
        result.append(nodeInfo);
    }
    return result;
}

Array php_qt_node_canvas_get_connections(int64_t canvas) {
    std::lock_guard<std::mutex> lock(g_nodeMutex);
    auto it = g_canvases.find(canvas);
    Array result;
    if (it == g_canvases.end()) return result;

    for (auto& pair : it->second.connections) {
        Array connInfo;
        connInfo["id"] = pair.second.id;
        connInfo["source_node"] = pair.second.sourceNode;
        connInfo["source_port"] = pair.second.sourcePort;
        connInfo["target_node"] = pair.second.targetNode;
        connInfo["target_port"] = pair.second.targetPort;
        result.append(connInfo);
    }
    return result;
}

void php_qt_node_canvas_clear(int64_t canvas) {
    std::lock_guard<std::mutex> lock(g_nodeMutex);
    auto it = g_canvases.find(canvas);
    if (it == g_canvases.end()) return;

    it->second.scene->clear();
    it->second.nodes.clear();
    it->second.connections.clear();
}

void php_qt_node_canvas_set_grid_visible(int64_t canvas, bool visible) {
    std::lock_guard<std::mutex> lock(g_nodeMutex);
    auto it = g_canvases.find(canvas);
    if (it != g_canvases.end()) {
        it->second.gridVisible = visible;
        it->second.view->setBackgroundBrush(
            visible ? QColor(40, 40, 40) : QColor(30, 30, 30));
    }
}

void php_qt_node_canvas_set_snap_to_grid(int64_t canvas, bool snap, int64_t gridSize) {
    std::lock_guard<std::mutex> lock(g_nodeMutex);
    auto it = g_canvases.find(canvas);
    if (it != g_canvases.end()) {
        it->second.snapEnabled = snap;
        it->second.gridSize = gridSize;
    }
}

void php_qt_node_canvas_set_zoom(int64_t canvas, double factor) {
    std::lock_guard<std::mutex> lock(g_nodeMutex);
    auto it = g_canvases.find(canvas);
    if (it != g_canvases.end()) {
        it->second.zoom = factor;
        it->second.view->resetTransform();
        it->second.view->scale(factor, factor);
    }
}

void php_qt_node_canvas_fit_in_view(int64_t canvas) {
    std::lock_guard<std::mutex> lock(g_nodeMutex);
    auto it = g_canvases.find(canvas);
    if (it != g_canvases.end()) {
        it->second.view->fitInView(it->second.scene->itemsBoundingRect(), Qt::KeepAspectRatio);
    }
}

void php_qt_node_canvas_show(int64_t canvas) {
    std::lock_guard<std::mutex> lock(g_nodeMutex);
    auto it = g_canvases.find(canvas);
    if (it == g_canvases.end()) return;

    CanvasInfo& c = it->second;
    if (!c.window) {
        QMainWindow* win = new QMainWindow();
        win->setWindowTitle("H3 Node Editor");
        win->setCentralWidget(c.view);
        win->resize(1200, 700);
        c.window = win;
    }
    c.window->show();
}

Variant php_qt_node_canvas_poll_event() {
    std::lock_guard<std::mutex> lock(g_eventMutex);
    if (g_eventQueue.empty()) {
        return Variant(nullptr);
    }

    QString event = g_eventQueue.front();
    g_eventQueue.pop();

    // Parse event string
    Array result;
    QStringList parts = event.split(':');
    if (parts.size() >= 1) {
        result["type"] = parts[0].toStdString();
        if (parts.size() >= 2) result["node_id"] = parts[1].toStdString();
        if (parts.size() >= 3) result["x"] = parts[2].toInt();
        if (parts.size() >= 4) result["y"] = parts[3].toInt();
    }
    return result;
}

void php_qt_node_canvas_set_node_selected(int64_t canvas, String nodeId, bool selected) {
    std::lock_guard<std::mutex> lock(g_nodeMutex);
    auto it = g_canvases.find(canvas);
    if (it == g_canvases.end()) return;

    auto nit = it->second.nodes.find(nodeId.toStdString());
    if (nit != it->second.nodes.end()) {
        nit->second.selected = selected;
        nit->second.item->setSelected(selected);
    }
}

void php_qt_node_canvas_set_node_param(int64_t canvas, String nodeId, String paramName, Variant value) {
    // Store params in node info (extend NodeInfo if needed)
    // For now, params are managed in PHP side
}

Variant php_qt_node_canvas_get_node_param(int64_t canvas, String nodeId, String paramName) {
    // Return from PHP-managed param store
    return Variant("");
}

void php_qt_node_canvas_destroy(int64_t canvas) {
    std::lock_guard<std::mutex> lock(g_nodeMutex);
    auto it = g_canvases.find(canvas);
    if (it == g_canvases.end()) return;

    it->second.scene->clear();
    if (it->second.window) {
        delete it->second.window; // takes ownership of the view
    } else {
        delete it->second.view;
    }
    delete it->second.scene;
    g_canvases.erase(it);
}
