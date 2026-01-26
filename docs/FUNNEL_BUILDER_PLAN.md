# Visual Workflow/Automation Builder - Detailed Technical Plan

## Overview

This document outlines the detailed technical plan for implementing a visual workflow/automation builder using **React Flow**, integrated with the existing Laravel backend and OnSend WhatsApp integration. This is for CRM automation workflows (triggers, actions, conditions), not sales page funnels.

---

## Part 1: Technology Stack

### Frontend
- **React Flow** - Visual workflow canvas (drag-and-drop nodes)
- **Zustand** - State management for workflow editor
- **Inertia.js** - Already in use for Laravel-React integration
- **Tailwind CSS v4** - Styling (existing)
- **Headless UI** - Accessible UI components

### Backend
- **Laravel 12** - API endpoints and business logic
- **Laravel Queues** - Async workflow execution
- **Redis** - Workflow state caching and queue driver
- **MySQL/PostgreSQL** - Persistent storage

---

## Part 2: Database Schema Design

### Core Tables

```sql
-- Workflow definitions
CREATE TABLE workflows (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    uuid CHAR(36) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    type ENUM('automation', 'funnel', 'sequence', 'broadcast') DEFAULT 'automation',
    status ENUM('draft', 'active', 'paused', 'archived') DEFAULT 'draft',
    trigger_type VARCHAR(100) NOT NULL,
    trigger_config JSON NULL,
    canvas_data JSON NULL, -- React Flow nodes and edges
    settings JSON NULL,
    stats JSON NULL, -- Cached performance stats
    created_by BIGINT UNSIGNED NULL,
    published_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_workflows_status (status),
    INDEX idx_workflows_type (type),
    INDEX idx_workflows_trigger (trigger_type)
);

-- Workflow steps/nodes
CREATE TABLE workflow_steps (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    workflow_id BIGINT UNSIGNED NOT NULL,
    uuid CHAR(36) UNIQUE NOT NULL,
    node_id VARCHAR(100) NOT NULL, -- React Flow node ID
    type VARCHAR(50) NOT NULL, -- trigger, action, condition, delay, split
    action_type VARCHAR(100) NULL, -- send_email, send_whatsapp, add_tag, etc.
    name VARCHAR(255) NULL,
    config JSON NULL, -- Step-specific configuration
    position_x INT DEFAULT 0,
    position_y INT DEFAULT 0,
    order_index INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (workflow_id) REFERENCES workflows(id) ON DELETE CASCADE,
    INDEX idx_steps_workflow (workflow_id),
    INDEX idx_steps_type (type),
    UNIQUE KEY uk_workflow_node (workflow_id, node_id)
);

-- Step connections/edges
CREATE TABLE workflow_connections (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    workflow_id BIGINT UNSIGNED NOT NULL,
    source_step_id BIGINT UNSIGNED NOT NULL,
    target_step_id BIGINT UNSIGNED NOT NULL,
    source_handle VARCHAR(50) NULL, -- For condition branches (yes/no)
    target_handle VARCHAR(50) NULL,
    label VARCHAR(100) NULL,
    condition_config JSON NULL, -- For conditional edges
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (workflow_id) REFERENCES workflows(id) ON DELETE CASCADE,
    FOREIGN KEY (source_step_id) REFERENCES workflow_steps(id) ON DELETE CASCADE,
    FOREIGN KEY (target_step_id) REFERENCES workflow_steps(id) ON DELETE CASCADE,
    INDEX idx_connections_workflow (workflow_id)
);

-- Contacts enrolled in workflows
CREATE TABLE workflow_enrollments (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    workflow_id BIGINT UNSIGNED NOT NULL,
    student_id BIGINT UNSIGNED NOT NULL,
    current_step_id BIGINT UNSIGNED NULL,
    status ENUM('active', 'completed', 'paused', 'failed', 'exited') DEFAULT 'active',
    entered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    exited_at TIMESTAMP NULL,
    exit_reason VARCHAR(255) NULL,
    metadata JSON NULL, -- Entry data, variables
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (workflow_id) REFERENCES workflows(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (current_step_id) REFERENCES workflow_steps(id) ON DELETE SET NULL,
    INDEX idx_enrollments_workflow (workflow_id),
    INDEX idx_enrollments_student (student_id),
    INDEX idx_enrollments_status (status),
    UNIQUE KEY uk_active_enrollment (workflow_id, student_id, status)
);

-- Step execution history
CREATE TABLE workflow_step_executions (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    enrollment_id BIGINT UNSIGNED NOT NULL,
    step_id BIGINT UNSIGNED NOT NULL,
    status ENUM('pending', 'processing', 'completed', 'failed', 'skipped') DEFAULT 'pending',
    scheduled_at TIMESTAMP NULL,
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    result JSON NULL, -- Execution result/error
    attempts INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (enrollment_id) REFERENCES workflow_enrollments(id) ON DELETE CASCADE,
    FOREIGN KEY (step_id) REFERENCES workflow_steps(id) ON DELETE CASCADE,
    INDEX idx_executions_enrollment (enrollment_id),
    INDEX idx_executions_step (step_id),
    INDEX idx_executions_status (status),
    INDEX idx_executions_scheduled (scheduled_at)
);

-- Contact tags
CREATE TABLE tags (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    color VARCHAR(7) DEFAULT '#6366f1',
    description TEXT NULL,
    type ENUM('manual', 'auto', 'system') DEFAULT 'manual',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_tags_slug (slug),
    INDEX idx_tags_type (type)
);

-- Tag assignments
CREATE TABLE student_tags (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    student_id BIGINT UNSIGNED NOT NULL,
    tag_id BIGINT UNSIGNED NOT NULL,
    applied_by BIGINT UNSIGNED NULL, -- user_id or null for auto
    source VARCHAR(50) NULL, -- 'workflow', 'manual', 'import'
    workflow_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE,
    FOREIGN KEY (applied_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (workflow_id) REFERENCES workflows(id) ON DELETE SET NULL,
    UNIQUE KEY uk_student_tag (student_id, tag_id),
    INDEX idx_student_tags_student (student_id),
    INDEX idx_student_tags_tag (tag_id)
);

-- Lead scoring rules
CREATE TABLE lead_scoring_rules (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    event_type VARCHAR(100) NOT NULL,
    conditions JSON NULL,
    points INT NOT NULL, -- Can be negative
    is_active BOOLEAN DEFAULT TRUE,
    expires_after_days INT NULL, -- Points expire after X days
    max_occurrences INT NULL, -- Max times this rule can apply
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_scoring_event (event_type),
    INDEX idx_scoring_active (is_active)
);

-- Lead scores per student
CREATE TABLE student_lead_scores (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    student_id BIGINT UNSIGNED NOT NULL,
    total_score INT DEFAULT 0,
    engagement_score INT DEFAULT 0,
    purchase_score INT DEFAULT 0,
    activity_score INT DEFAULT 0,
    last_activity_at TIMESTAMP NULL,
    grade ENUM('hot', 'warm', 'cold', 'inactive') DEFAULT 'cold',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    UNIQUE KEY uk_student_score (student_id),
    INDEX idx_scores_grade (grade),
    INDEX idx_scores_total (total_score DESC)
);

-- Score history for decay and audit
CREATE TABLE lead_score_history (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    student_id BIGINT UNSIGNED NOT NULL,
    rule_id BIGINT UNSIGNED NULL,
    event_type VARCHAR(100) NOT NULL,
    points INT NOT NULL,
    reason VARCHAR(255) NULL,
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (rule_id) REFERENCES lead_scoring_rules(id) ON DELETE SET NULL,
    INDEX idx_score_history_student (student_id),
    INDEX idx_score_history_expires (expires_at)
);

-- Message templates
CREATE TABLE message_templates (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    channel ENUM('email', 'whatsapp', 'sms') NOT NULL,
    subject VARCHAR(500) NULL, -- For email
    content TEXT NOT NULL,
    content_html TEXT NULL, -- For email
    variables JSON NULL, -- Available merge tags
    category VARCHAR(100) NULL,
    is_active BOOLEAN DEFAULT TRUE,
    stats JSON NULL, -- Opens, clicks, etc.
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_templates_channel (channel),
    INDEX idx_templates_category (category)
);

-- Contact custom fields
CREATE TABLE custom_fields (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    type ENUM('text', 'number', 'date', 'boolean', 'select', 'multiselect') NOT NULL,
    options JSON NULL, -- For select/multiselect
    default_value VARCHAR(255) NULL,
    is_required BOOLEAN DEFAULT FALSE,
    is_filterable BOOLEAN DEFAULT TRUE,
    order_index INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_custom_fields_slug (slug)
);

-- Custom field values
CREATE TABLE student_custom_fields (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    student_id BIGINT UNSIGNED NOT NULL,
    custom_field_id BIGINT UNSIGNED NOT NULL,
    value TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (custom_field_id) REFERENCES custom_fields(id) ON DELETE CASCADE,
    UNIQUE KEY uk_student_custom_field (student_id, custom_field_id),
    INDEX idx_custom_field_values (custom_field_id)
);

-- Activity log for contacts
CREATE TABLE contact_activities (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    student_id BIGINT UNSIGNED NOT NULL,
    type VARCHAR(100) NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    metadata JSON NULL,
    performed_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (performed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_activities_student (student_id),
    INDEX idx_activities_type (type),
    INDEX idx_activities_created (created_at DESC)
);

-- Communication log (all channels)
CREATE TABLE communication_logs (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    student_id BIGINT UNSIGNED NOT NULL,
    channel ENUM('email', 'whatsapp', 'sms', 'in_app') NOT NULL,
    direction ENUM('outbound', 'inbound') DEFAULT 'outbound',
    template_id BIGINT UNSIGNED NULL,
    workflow_id BIGINT UNSIGNED NULL,
    step_execution_id BIGINT UNSIGNED NULL,
    external_id VARCHAR(255) NULL, -- Provider message ID
    recipient VARCHAR(255) NOT NULL, -- Email/phone
    subject VARCHAR(500) NULL,
    content TEXT NULL,
    status ENUM('queued', 'sent', 'delivered', 'failed', 'opened', 'clicked', 'bounced', 'complained') DEFAULT 'queued',
    status_details JSON NULL,
    sent_at TIMESTAMP NULL,
    delivered_at TIMESTAMP NULL,
    opened_at TIMESTAMP NULL,
    clicked_at TIMESTAMP NULL,
    metadata JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (template_id) REFERENCES message_templates(id) ON DELETE SET NULL,
    FOREIGN KEY (workflow_id) REFERENCES workflows(id) ON DELETE SET NULL,
    INDEX idx_comm_student (student_id),
    INDEX idx_comm_channel (channel),
    INDEX idx_comm_status (status),
    INDEX idx_comm_workflow (workflow_id),
    INDEX idx_comm_external (external_id)
);

-- Segments for dynamic audience building
CREATE TABLE segments (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    type ENUM('static', 'dynamic') DEFAULT 'dynamic',
    conditions JSON NOT NULL, -- Filter conditions
    contact_count INT DEFAULT 0,
    last_calculated_at TIMESTAMP NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_segments_type (type)
);
```

---

## Part 3: React Flow Canvas Architecture

### Node Types

```typescript
// types/workflow.ts

export type NodeType =
  | 'trigger'
  | 'action'
  | 'condition'
  | 'delay'
  | 'split'
  | 'goal'
  | 'exit';

export type TriggerType =
  | 'contact_created'
  | 'contact_updated'
  | 'tag_added'
  | 'tag_removed'
  | 'form_submitted'
  | 'order_placed'
  | 'order_paid'
  | 'order_cancelled'
  | 'class_enrolled'
  | 'class_completed'
  | 'attendance_marked'
  | 'email_opened'
  | 'email_clicked'
  | 'whatsapp_replied'
  | 'date_trigger'
  | 'score_changed'
  | 'manual_trigger';

export type ActionType =
  // Communication
  | 'send_email'
  | 'send_whatsapp'
  | 'send_sms'
  | 'send_notification'
  // Contact Management
  | 'add_tag'
  | 'remove_tag'
  | 'update_field'
  | 'update_score'
  | 'add_to_segment'
  | 'remove_from_segment'
  // Enrollment
  | 'enroll_in_class'
  | 'remove_from_class'
  | 'create_enrollment'
  // Flow Control
  | 'start_workflow'
  | 'stop_workflow'
  | 'webhook'
  | 'create_task'
  | 'notify_team';

export interface WorkflowNode {
  id: string;
  type: NodeType;
  position: { x: number; y: number };
  data: {
    label: string;
    nodeType: NodeType;
    triggerType?: TriggerType;
    actionType?: ActionType;
    config: Record<string, any>;
    isConfigured: boolean;
  };
}

export interface WorkflowEdge {
  id: string;
  source: string;
  target: string;
  sourceHandle?: string;
  targetHandle?: string;
  label?: string;
  data?: {
    condition?: ConditionConfig;
  };
}
```

### Custom Node Components

```tsx
// components/workflow/nodes/TriggerNode.tsx

import { memo } from 'react';
import { Handle, Position, NodeProps } from 'reactflow';
import { PlayIcon, UserPlusIcon, TagIcon, EnvelopeOpenIcon, ShoppingCartIcon } from '@heroicons/react/24/solid';

const triggerIcons: Record<string, any> = {
  contact_created: UserPlusIcon,
  order_placed: ShoppingCartIcon,
  tag_added: TagIcon,
  email_opened: EnvelopeOpenIcon,
  // ... more icons
};

export const TriggerNode = memo(({ data, selected }: NodeProps) => {
  const Icon = triggerIcons[data.triggerType] || PlayIcon;

  return (
    <div className={`
      px-4 py-3 rounded-lg border-2 min-w-[200px]
      ${selected ? 'border-indigo-500 shadow-lg' : 'border-gray-200'}
      ${data.isConfigured ? 'bg-green-50' : 'bg-white'}
    `}>
      <div className="flex items-center gap-3">
        <div className="p-2 rounded-lg bg-indigo-100">
          <Icon className="w-5 h-5 text-indigo-600" />
        </div>
        <div>
          <p className="text-xs font-medium text-gray-500 uppercase">Trigger</p>
          <p className="text-sm font-semibold text-gray-900">{data.label}</p>
        </div>
      </div>

      {!data.isConfigured && (
        <p className="mt-2 text-xs text-amber-600">Click to configure</p>
      )}

      <Handle
        type="source"
        position={Position.Bottom}
        className="w-3 h-3 bg-indigo-500 border-2 border-white"
      />
    </div>
  );
});

// components/workflow/nodes/ActionNode.tsx

export const ActionNode = memo(({ data, selected }: NodeProps) => {
  const getActionColor = (actionType: string) => {
    if (actionType.startsWith('send_')) return 'blue';
    if (actionType.includes('tag')) return 'purple';
    if (actionType.includes('score')) return 'orange';
    return 'gray';
  };

  const color = getActionColor(data.actionType);

  return (
    <div className={`
      px-4 py-3 rounded-lg border-2 min-w-[200px]
      ${selected ? `border-${color}-500 shadow-lg` : 'border-gray-200'}
      bg-white
    `}>
      <Handle
        type="target"
        position={Position.Top}
        className="w-3 h-3 bg-gray-400 border-2 border-white"
      />

      <div className="flex items-center gap-3">
        <div className={`p-2 rounded-lg bg-${color}-100`}>
          <ActionIcon type={data.actionType} className={`w-5 h-5 text-${color}-600`} />
        </div>
        <div>
          <p className="text-xs font-medium text-gray-500 uppercase">Action</p>
          <p className="text-sm font-semibold text-gray-900">{data.label}</p>
        </div>
      </div>

      <Handle
        type="source"
        position={Position.Bottom}
        className="w-3 h-3 bg-gray-400 border-2 border-white"
      />
    </div>
  );
});

// components/workflow/nodes/ConditionNode.tsx

export const ConditionNode = memo(({ data, selected }: NodeProps) => {
  return (
    <div className={`
      px-4 py-3 rounded-lg border-2 min-w-[220px]
      ${selected ? 'border-amber-500 shadow-lg' : 'border-gray-200'}
      bg-amber-50
    `}>
      <Handle
        type="target"
        position={Position.Top}
        className="w-3 h-3 bg-amber-500 border-2 border-white"
      />

      <div className="flex items-center gap-3">
        <div className="p-2 rounded-lg bg-amber-100">
          <QuestionMarkCircleIcon className="w-5 h-5 text-amber-600" />
        </div>
        <div>
          <p className="text-xs font-medium text-amber-700 uppercase">Condition</p>
          <p className="text-sm font-semibold text-gray-900">{data.label}</p>
        </div>
      </div>

      {data.config?.summary && (
        <p className="mt-2 text-xs text-gray-600">{data.config.summary}</p>
      )}

      <div className="flex justify-between mt-3">
        <div className="text-center">
          <Handle
            type="source"
            position={Position.Bottom}
            id="yes"
            className="w-3 h-3 bg-green-500 border-2 border-white"
            style={{ left: '25%' }}
          />
          <span className="text-xs text-green-600 font-medium">Yes</span>
        </div>
        <div className="text-center">
          <Handle
            type="source"
            position={Position.Bottom}
            id="no"
            className="w-3 h-3 bg-red-500 border-2 border-white"
            style={{ left: '75%' }}
          />
          <span className="text-xs text-red-600 font-medium">No</span>
        </div>
      </div>
    </div>
  );
});

// components/workflow/nodes/DelayNode.tsx

export const DelayNode = memo(({ data, selected }: NodeProps) => {
  const formatDelay = (config: any) => {
    if (config.type === 'duration') {
      return `Wait ${config.value} ${config.unit}`;
    }
    if (config.type === 'until_date') {
      return `Wait until ${config.date}`;
    }
    if (config.type === 'until_time') {
      return `Wait until ${config.time}`;
    }
    return 'Configure delay';
  };

  return (
    <div className={`
      px-4 py-3 rounded-lg border-2 min-w-[180px]
      ${selected ? 'border-cyan-500 shadow-lg' : 'border-gray-200'}
      bg-cyan-50
    `}>
      <Handle type="target" position={Position.Top} />

      <div className="flex items-center gap-3">
        <div className="p-2 rounded-lg bg-cyan-100">
          <ClockIcon className="w-5 h-5 text-cyan-600" />
        </div>
        <div>
          <p className="text-xs font-medium text-cyan-700 uppercase">Delay</p>
          <p className="text-sm font-semibold text-gray-900">{formatDelay(data.config)}</p>
        </div>
      </div>

      <Handle type="source" position={Position.Bottom} />
    </div>
  );
});
```

### Zustand Store

```typescript
// stores/workflowStore.ts

import { create } from 'zustand';
import { devtools, persist } from 'zustand/middleware';
import {
  Node,
  Edge,
  addEdge,
  applyNodeChanges,
  applyEdgeChanges,
  Connection,
  NodeChange,
  EdgeChange,
} from 'reactflow';

interface WorkflowState {
  // Workflow metadata
  workflowId: string | null;
  workflowName: string;
  workflowStatus: 'draft' | 'active' | 'paused' | 'archived';

  // React Flow state
  nodes: Node[];
  edges: Edge[];

  // UI state
  selectedNodeId: string | null;
  isPanelOpen: boolean;
  panelMode: 'add' | 'edit' | null;

  // Undo/Redo
  history: { nodes: Node[]; edges: Edge[] }[];
  historyIndex: number;

  // Validation
  validationErrors: ValidationError[];

  // Actions
  setNodes: (nodes: Node[]) => void;
  setEdges: (edges: Edge[]) => void;
  onNodesChange: (changes: NodeChange[]) => void;
  onEdgesChange: (changes: EdgeChange[]) => void;
  onConnect: (connection: Connection) => void;

  addNode: (type: NodeType, position: { x: number; y: number }) => void;
  updateNodeData: (nodeId: string, data: Partial<NodeData>) => void;
  deleteNode: (nodeId: string) => void;

  selectNode: (nodeId: string | null) => void;
  openPanel: (mode: 'add' | 'edit') => void;
  closePanel: () => void;

  undo: () => void;
  redo: () => void;
  saveHistory: () => void;

  validateWorkflow: () => boolean;
  loadWorkflow: (workflow: Workflow) => void;
  resetWorkflow: () => void;

  // API actions
  saveWorkflow: () => Promise<void>;
  publishWorkflow: () => Promise<void>;
}

export const useWorkflowStore = create<WorkflowState>()(
  devtools(
    persist(
      (set, get) => ({
        // Initial state
        workflowId: null,
        workflowName: 'Untitled Workflow',
        workflowStatus: 'draft',
        nodes: [],
        edges: [],
        selectedNodeId: null,
        isPanelOpen: false,
        panelMode: null,
        history: [],
        historyIndex: -1,
        validationErrors: [],

        setNodes: (nodes) => set({ nodes }),
        setEdges: (edges) => set({ edges }),

        onNodesChange: (changes) => {
          set({
            nodes: applyNodeChanges(changes, get().nodes),
          });
        },

        onEdgesChange: (changes) => {
          set({
            edges: applyEdgeChanges(changes, get().edges),
          });
        },

        onConnect: (connection) => {
          // Validate connection
          const sourceNode = get().nodes.find(n => n.id === connection.source);
          const targetNode = get().nodes.find(n => n.id === connection.target);

          // Prevent connecting to trigger nodes
          if (targetNode?.data.nodeType === 'trigger') {
            return;
          }

          // Prevent multiple connections from non-condition nodes
          if (sourceNode?.data.nodeType !== 'condition') {
            const existingEdge = get().edges.find(
              e => e.source === connection.source && e.sourceHandle === connection.sourceHandle
            );
            if (existingEdge) {
              return;
            }
          }

          set({
            edges: addEdge(
              {
                ...connection,
                id: `edge-${Date.now()}`,
                animated: true,
              },
              get().edges
            ),
          });
          get().saveHistory();
        },

        addNode: (type, position) => {
          const newNode: Node = {
            id: `node-${Date.now()}`,
            type,
            position,
            data: {
              label: getDefaultLabel(type),
              nodeType: type,
              config: {},
              isConfigured: false,
            },
          };

          set({
            nodes: [...get().nodes, newNode],
            selectedNodeId: newNode.id,
            isPanelOpen: true,
            panelMode: 'edit',
          });
          get().saveHistory();
        },

        updateNodeData: (nodeId, data) => {
          set({
            nodes: get().nodes.map(node =>
              node.id === nodeId
                ? { ...node, data: { ...node.data, ...data } }
                : node
            ),
          });
          get().saveHistory();
        },

        deleteNode: (nodeId) => {
          set({
            nodes: get().nodes.filter(n => n.id !== nodeId),
            edges: get().edges.filter(
              e => e.source !== nodeId && e.target !== nodeId
            ),
            selectedNodeId: null,
          });
          get().saveHistory();
        },

        selectNode: (nodeId) => {
          set({
            selectedNodeId: nodeId,
            isPanelOpen: nodeId !== null,
            panelMode: nodeId ? 'edit' : null,
          });
        },

        openPanel: (mode) => set({ isPanelOpen: true, panelMode: mode }),
        closePanel: () => set({ isPanelOpen: false, panelMode: null }),

        undo: () => {
          const { history, historyIndex } = get();
          if (historyIndex > 0) {
            const newIndex = historyIndex - 1;
            set({
              nodes: history[newIndex].nodes,
              edges: history[newIndex].edges,
              historyIndex: newIndex,
            });
          }
        },

        redo: () => {
          const { history, historyIndex } = get();
          if (historyIndex < history.length - 1) {
            const newIndex = historyIndex + 1;
            set({
              nodes: history[newIndex].nodes,
              edges: history[newIndex].edges,
              historyIndex: newIndex,
            });
          }
        },

        saveHistory: () => {
          const { nodes, edges, history, historyIndex } = get();
          const newHistory = history.slice(0, historyIndex + 1);
          newHistory.push({ nodes: [...nodes], edges: [...edges] });

          // Keep only last 50 states
          if (newHistory.length > 50) {
            newHistory.shift();
          }

          set({
            history: newHistory,
            historyIndex: newHistory.length - 1,
          });
        },

        validateWorkflow: () => {
          const { nodes, edges } = get();
          const errors: ValidationError[] = [];

          // Check for trigger
          const triggers = nodes.filter(n => n.data.nodeType === 'trigger');
          if (triggers.length === 0) {
            errors.push({
              type: 'error',
              message: 'Workflow must have at least one trigger',
            });
          }

          // Check for unconfigured nodes
          nodes.forEach(node => {
            if (!node.data.isConfigured) {
              errors.push({
                type: 'warning',
                nodeId: node.id,
                message: `"${node.data.label}" is not configured`,
              });
            }
          });

          // Check for disconnected nodes (except triggers)
          nodes.forEach(node => {
            if (node.data.nodeType !== 'trigger') {
              const hasIncoming = edges.some(e => e.target === node.id);
              if (!hasIncoming) {
                errors.push({
                  type: 'error',
                  nodeId: node.id,
                  message: `"${node.data.label}" has no incoming connection`,
                });
              }
            }
          });

          set({ validationErrors: errors });
          return errors.filter(e => e.type === 'error').length === 0;
        },

        loadWorkflow: (workflow) => {
          set({
            workflowId: workflow.id,
            workflowName: workflow.name,
            workflowStatus: workflow.status,
            nodes: workflow.canvas_data?.nodes || [],
            edges: workflow.canvas_data?.edges || [],
            history: [],
            historyIndex: -1,
            validationErrors: [],
          });
          get().saveHistory();
        },

        resetWorkflow: () => {
          set({
            workflowId: null,
            workflowName: 'Untitled Workflow',
            workflowStatus: 'draft',
            nodes: [],
            edges: [],
            selectedNodeId: null,
            isPanelOpen: false,
            panelMode: null,
            history: [],
            historyIndex: -1,
            validationErrors: [],
          });
        },

        saveWorkflow: async () => {
          const state = get();
          const payload = {
            name: state.workflowName,
            canvas_data: {
              nodes: state.nodes,
              edges: state.edges,
            },
          };

          if (state.workflowId) {
            await axios.put(`/api/workflows/${state.workflowId}`, payload);
          } else {
            const response = await axios.post('/api/workflows', payload);
            set({ workflowId: response.data.id });
          }
        },

        publishWorkflow: async () => {
          const state = get();
          if (!state.validateWorkflow()) {
            throw new Error('Workflow has validation errors');
          }

          await state.saveWorkflow();
          await axios.post(`/api/workflows/${state.workflowId}/publish`);
          set({ workflowStatus: 'active' });
        },
      }),
      {
        name: 'workflow-storage',
        partialize: (state) => ({
          workflowId: state.workflowId,
          workflowName: state.workflowName,
          nodes: state.nodes,
          edges: state.edges,
        }),
      }
    )
  )
);
```

### Main Workflow Builder Component

```tsx
// components/workflow/WorkflowBuilder.tsx

import { useCallback, useRef, useEffect } from 'react';
import ReactFlow, {
  Background,
  Controls,
  MiniMap,
  Panel,
  useReactFlow,
  ReactFlowProvider,
} from 'reactflow';
import 'reactflow/dist/style.css';

import { useWorkflowStore } from '@/stores/workflowStore';
import { TriggerNode } from './nodes/TriggerNode';
import { ActionNode } from './nodes/ActionNode';
import { ConditionNode } from './nodes/ConditionNode';
import { DelayNode } from './nodes/DelayNode';
import { NodePanel } from './panels/NodePanel';
import { ConfigPanel } from './panels/ConfigPanel';
import { Toolbar } from './Toolbar';

const nodeTypes = {
  trigger: TriggerNode,
  action: ActionNode,
  condition: ConditionNode,
  delay: DelayNode,
};

function WorkflowBuilderInner() {
  const reactFlowWrapper = useRef<HTMLDivElement>(null);
  const { project } = useReactFlow();

  const {
    nodes,
    edges,
    onNodesChange,
    onEdgesChange,
    onConnect,
    addNode,
    selectNode,
    selectedNodeId,
    isPanelOpen,
    panelMode,
  } = useWorkflowStore();

  const onDragOver = useCallback((event: React.DragEvent) => {
    event.preventDefault();
    event.dataTransfer.dropEffect = 'move';
  }, []);

  const onDrop = useCallback(
    (event: React.DragEvent) => {
      event.preventDefault();

      const type = event.dataTransfer.getData('application/reactflow');
      if (!type || !reactFlowWrapper.current) return;

      const bounds = reactFlowWrapper.current.getBoundingClientRect();
      const position = project({
        x: event.clientX - bounds.left,
        y: event.clientY - bounds.top,
      });

      addNode(type as NodeType, position);
    },
    [project, addNode]
  );

  const onNodeClick = useCallback(
    (_: React.MouseEvent, node: Node) => {
      selectNode(node.id);
    },
    [selectNode]
  );

  const onPaneClick = useCallback(() => {
    selectNode(null);
  }, [selectNode]);

  return (
    <div className="h-screen flex">
      {/* Node Palette */}
      <NodePanel />

      {/* Canvas */}
      <div className="flex-1 relative" ref={reactFlowWrapper}>
        <ReactFlow
          nodes={nodes}
          edges={edges}
          onNodesChange={onNodesChange}
          onEdgesChange={onEdgesChange}
          onConnect={onConnect}
          onDragOver={onDragOver}
          onDrop={onDrop}
          onNodeClick={onNodeClick}
          onPaneClick={onPaneClick}
          nodeTypes={nodeTypes}
          fitView
          snapToGrid
          snapGrid={[15, 15]}
          defaultEdgeOptions={{
            animated: true,
            style: { strokeWidth: 2 },
          }}
        >
          <Background variant="dots" gap={15} size={1} />
          <Controls />
          <MiniMap
            nodeColor={(node) => {
              switch (node.data.nodeType) {
                case 'trigger': return '#6366f1';
                case 'action': return '#3b82f6';
                case 'condition': return '#f59e0b';
                case 'delay': return '#06b6d4';
                default: return '#9ca3af';
              }
            }}
          />
          <Panel position="top-center">
            <Toolbar />
          </Panel>
        </ReactFlow>
      </div>

      {/* Configuration Panel */}
      {isPanelOpen && panelMode === 'edit' && selectedNodeId && (
        <ConfigPanel nodeId={selectedNodeId} />
      )}
    </div>
  );
}

export function WorkflowBuilder({ workflow }: { workflow?: Workflow }) {
  const { loadWorkflow, resetWorkflow } = useWorkflowStore();

  useEffect(() => {
    if (workflow) {
      loadWorkflow(workflow);
    } else {
      resetWorkflow();
    }
  }, [workflow]);

  return (
    <ReactFlowProvider>
      <WorkflowBuilderInner />
    </ReactFlowProvider>
  );
}
```

---

## Part 4: Trigger System

### Available Triggers

| Trigger | Description | Configuration |
|---------|-------------|---------------|
| `contact_created` | When a new student is created | Filters: source, tags |
| `contact_updated` | When student data changes | Fields to watch |
| `tag_added` | When tag is applied | Specific tag(s) |
| `tag_removed` | When tag is removed | Specific tag(s) |
| `form_submitted` | External form submission | Form ID, webhook |
| `order_placed` | New order created | Product filter, amount range |
| `order_paid` | Order payment confirmed | Product filter, amount range |
| `order_cancelled` | Order cancelled/refunded | - |
| `class_enrolled` | Enrolled in class | Class filter |
| `class_completed` | Completed class | Class filter |
| `attendance_marked` | Attendance recorded | Status filter |
| `email_opened` | Email was opened | Email/template filter |
| `email_clicked` | Link clicked in email | Link filter |
| `whatsapp_replied` | Reply to WhatsApp | Keyword filter |
| `date_trigger` | Specific date/time | Date field, offset |
| `score_changed` | Lead score updated | Threshold, direction |
| `manual_trigger` | Manually add contact | - |

### Trigger Implementation

```php
// app/Services/Workflow/TriggerRegistry.php

namespace App\Services\Workflow;

use App\Events\ContactCreated;
use App\Events\OrderPaid;
use App\Events\TagAdded;

class TriggerRegistry
{
    protected array $triggers = [];

    public function __construct()
    {
        $this->registerDefaultTriggers();
    }

    protected function registerDefaultTriggers(): void
    {
        $this->register('contact_created', [
            'event' => ContactCreated::class,
            'handler' => Triggers\ContactCreatedTrigger::class,
            'label' => 'Contact Created',
            'description' => 'Triggers when a new contact is added',
            'icon' => 'user-plus',
            'config_schema' => [
                'source' => ['type' => 'select', 'options' => ['any', 'manual', 'import', 'api']],
                'tags' => ['type' => 'tags', 'multiple' => true],
            ],
        ]);

        $this->register('order_paid', [
            'event' => OrderPaid::class,
            'handler' => Triggers\OrderPaidTrigger::class,
            'label' => 'Order Paid',
            'description' => 'Triggers when an order payment is confirmed',
            'icon' => 'credit-card',
            'config_schema' => [
                'products' => ['type' => 'products', 'multiple' => true],
                'min_amount' => ['type' => 'number', 'label' => 'Minimum Amount'],
                'max_amount' => ['type' => 'number', 'label' => 'Maximum Amount'],
            ],
        ]);

        // ... more triggers
    }

    public function register(string $type, array $config): void
    {
        $this->triggers[$type] = $config;
    }

    public function get(string $type): ?array
    {
        return $this->triggers[$type] ?? null;
    }

    public function all(): array
    {
        return $this->triggers;
    }
}

// app/Services/Workflow/Triggers/OrderPaidTrigger.php

namespace App\Services\Workflow\Triggers;

use App\Models\ProductOrder;
use App\Models\Workflow;

class OrderPaidTrigger implements TriggerInterface
{
    public function shouldTrigger(Workflow $workflow, $event): bool
    {
        $order = $event->order;
        $config = $workflow->trigger_config;

        // Check product filter
        if (!empty($config['products'])) {
            $orderProductIds = $order->items->pluck('product_id')->toArray();
            if (empty(array_intersect($config['products'], $orderProductIds))) {
                return false;
            }
        }

        // Check amount range
        if (isset($config['min_amount']) && $order->total_amount < $config['min_amount']) {
            return false;
        }

        if (isset($config['max_amount']) && $order->total_amount > $config['max_amount']) {
            return false;
        }

        return true;
    }

    public function getContactId($event): int
    {
        return $event->order->student_id;
    }

    public function getEventData($event): array
    {
        $order = $event->order;
        return [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'total_amount' => $order->total_amount,
            'products' => $order->items->map(fn($i) => $i->product_name)->toArray(),
        ];
    }
}
```

---

## Part 5: Action System

### Communication Actions

```php
// app/Services/Workflow/Actions/SendWhatsAppAction.php

namespace App\Services\Workflow\Actions;

use App\Models\Student;
use App\Models\MessageTemplate;
use App\Services\WhatsAppService;
use App\Jobs\SendWhatsAppNotificationJob;

class SendWhatsAppAction implements ActionInterface
{
    public function __construct(
        protected WhatsAppService $whatsApp
    ) {}

    public function execute(ActionContext $context): ActionResult
    {
        $student = $context->getStudent();
        $config = $context->getConfig();

        // Get phone number
        $phone = $student->phone ?? $student->user->phone;
        if (!$phone) {
            return ActionResult::skipped('No phone number available');
        }

        // Get template and merge variables
        $template = MessageTemplate::find($config['template_id']);
        $message = $this->mergeVariables($template->content, $context);

        // Determine if using OnSend or other provider
        $provider = $config['provider'] ?? 'onsend';

        // Queue the message
        SendWhatsAppNotificationJob::dispatch(
            phone: $phone,
            message: $message,
            studentId: $student->id,
            workflowId: $context->getWorkflowId(),
            stepExecutionId: $context->getStepExecutionId(),
            provider: $provider
        );

        // Log communication
        $context->logCommunication('whatsapp', $phone, $message);

        return ActionResult::success([
            'phone' => $phone,
            'message_preview' => substr($message, 0, 100),
        ]);
    }

    protected function mergeVariables(string $content, ActionContext $context): string
    {
        $student = $context->getStudent();
        $eventData = $context->getEventData();

        $variables = [
            '{{name}}' => $student->user->name,
            '{{first_name}}' => explode(' ', $student->user->name)[0],
            '{{email}}' => $student->user->email,
            '{{phone}}' => $student->phone,
            '{{student_id}}' => $student->student_id,
            '{{order_number}}' => $eventData['order_number'] ?? '',
            '{{order_amount}}' => isset($eventData['total_amount'])
                ? 'RM ' . number_format($eventData['total_amount'], 2)
                : '',
            '{{class_name}}' => $eventData['class_name'] ?? '',
        ];

        return str_replace(array_keys($variables), array_values($variables), $content);
    }

    public static function getConfigSchema(): array
    {
        return [
            'template_id' => [
                'type' => 'template_select',
                'channel' => 'whatsapp',
                'required' => true,
            ],
            'provider' => [
                'type' => 'select',
                'options' => [
                    'onsend' => 'OnSend.io',
                    // Future providers
                ],
                'default' => 'onsend',
            ],
            'delay_seconds' => [
                'type' => 'number',
                'label' => 'Delay (seconds)',
                'description' => 'Add random delay for anti-ban',
                'min' => 0,
                'max' => 300,
                'default' => 0,
            ],
        ];
    }
}
```

### Contact Management Actions

```php
// app/Services/Workflow/Actions/AddTagAction.php

class AddTagAction implements ActionInterface
{
    public function execute(ActionContext $context): ActionResult
    {
        $student = $context->getStudent();
        $config = $context->getConfig();

        $tagIds = (array) $config['tag_ids'];

        foreach ($tagIds as $tagId) {
            // Check if already has tag
            if ($student->tags()->where('tag_id', $tagId)->exists()) {
                continue;
            }

            $student->tags()->attach($tagId, [
                'source' => 'workflow',
                'workflow_id' => $context->getWorkflowId(),
            ]);

            // Trigger tag_added event for chaining
            event(new TagAdded($student, $tagId));
        }

        // Log activity
        $context->logActivity('tags_added', 'Tags added via workflow', [
            'tag_ids' => $tagIds,
        ]);

        return ActionResult::success(['tags_added' => $tagIds]);
    }

    public static function getConfigSchema(): array
    {
        return [
            'tag_ids' => [
                'type' => 'tags',
                'multiple' => true,
                'required' => true,
                'allow_create' => true,
            ],
        ];
    }
}

// app/Services/Workflow/Actions/UpdateScoreAction.php

class UpdateScoreAction implements ActionInterface
{
    public function execute(ActionContext $context): ActionResult
    {
        $student = $context->getStudent();
        $config = $context->getConfig();

        $scoreRecord = $student->leadScore ?? StudentLeadScore::create([
            'student_id' => $student->id,
        ]);

        $points = $config['points'];
        $category = $config['category'] ?? 'activity';

        // Apply points
        $scoreRecord->increment("{$category}_score", $points);
        $scoreRecord->increment('total_score', $points);

        // Recalculate grade
        $scoreRecord->grade = $this->calculateGrade($scoreRecord->total_score);
        $scoreRecord->last_activity_at = now();
        $scoreRecord->save();

        // Log history
        LeadScoreHistory::create([
            'student_id' => $student->id,
            'event_type' => 'workflow_action',
            'points' => $points,
            'reason' => $config['reason'] ?? 'Updated by workflow',
        ]);

        // Trigger score_changed event
        event(new ScoreChanged($student, $scoreRecord));

        return ActionResult::success([
            'points_added' => $points,
            'new_total' => $scoreRecord->total_score,
            'grade' => $scoreRecord->grade,
        ]);
    }

    protected function calculateGrade(int $score): string
    {
        return match(true) {
            $score >= 100 => 'hot',
            $score >= 50 => 'warm',
            $score >= 20 => 'cold',
            default => 'inactive',
        };
    }
}
```

---

## Part 6: Condition System

```php
// app/Services/Workflow/ConditionEvaluator.php

namespace App\Services\Workflow;

use App\Models\Student;

class ConditionEvaluator
{
    public function evaluate(Student $student, array $conditions, array $eventData = []): bool
    {
        $logic = $conditions['logic'] ?? 'and';
        $rules = $conditions['rules'] ?? [];

        if (empty($rules)) {
            return true;
        }

        $results = [];

        foreach ($rules as $rule) {
            $results[] = $this->evaluateRule($student, $rule, $eventData);
        }

        return $logic === 'and'
            ? !in_array(false, $results, true)
            : in_array(true, $results, true);
    }

    protected function evaluateRule(Student $student, array $rule, array $eventData): bool
    {
        $field = $rule['field'];
        $operator = $rule['operator'];
        $value = $rule['value'];

        // Get actual value based on field type
        $actualValue = match($rule['type'] ?? 'contact') {
            'contact' => $this->getContactFieldValue($student, $field),
            'tag' => $this->hasTag($student, $field),
            'score' => $this->getScoreValue($student, $field),
            'order' => $this->getOrderValue($student, $field),
            'event' => $eventData[$field] ?? null,
            'custom_field' => $this->getCustomFieldValue($student, $field),
            default => null,
        };

        return $this->compareValues($actualValue, $operator, $value);
    }

    protected function getContactFieldValue(Student $student, string $field)
    {
        return match($field) {
            'name' => $student->user->name,
            'email' => $student->user->email,
            'phone' => $student->phone,
            'country' => $student->country,
            'city' => $student->city,
            'state' => $student->state,
            'created_at' => $student->created_at,
            'total_spent' => $student->orders()->whereIn('status', ['paid', 'completed'])->sum('total_amount'),
            'order_count' => $student->orders()->count(),
            'class_count' => $student->activeClasses()->count(),
            default => $student->{$field} ?? null,
        };
    }

    protected function hasTag(Student $student, string $tagSlug): bool
    {
        return $student->tags()->where('slug', $tagSlug)->exists();
    }

    protected function getScoreValue(Student $student, string $field)
    {
        $score = $student->leadScore;
        if (!$score) return 0;

        return match($field) {
            'total' => $score->total_score,
            'engagement' => $score->engagement_score,
            'purchase' => $score->purchase_score,
            'activity' => $score->activity_score,
            'grade' => $score->grade,
            default => $score->total_score,
        };
    }

    protected function compareValues($actual, string $operator, $expected): bool
    {
        return match($operator) {
            'equals', '=' => $actual == $expected,
            'not_equals', '!=' => $actual != $expected,
            'contains' => str_contains(strtolower((string)$actual), strtolower((string)$expected)),
            'not_contains' => !str_contains(strtolower((string)$actual), strtolower((string)$expected)),
            'starts_with' => str_starts_with(strtolower((string)$actual), strtolower((string)$expected)),
            'ends_with' => str_ends_with(strtolower((string)$actual), strtolower((string)$expected)),
            'greater_than', '>' => $actual > $expected,
            'less_than', '<' => $actual < $expected,
            'greater_or_equal', '>=' => $actual >= $expected,
            'less_or_equal', '<=' => $actual <= $expected,
            'is_empty' => empty($actual),
            'is_not_empty' => !empty($actual),
            'in' => in_array($actual, (array)$expected),
            'not_in' => !in_array($actual, (array)$expected),
            'before' => $actual < $expected, // For dates
            'after' => $actual > $expected,  // For dates
            'has_tag' => $actual === true,
            'no_tag' => $actual === false,
            default => false,
        };
    }
}
```

---

## Part 7: Workflow Execution Engine

```php
// app/Services/Workflow/WorkflowEngine.php

namespace App\Services\Workflow;

use App\Models\Workflow;
use App\Models\WorkflowEnrollment;
use App\Models\WorkflowStepExecution;
use App\Models\Student;
use Illuminate\Support\Facades\DB;

class WorkflowEngine
{
    public function __construct(
        protected TriggerRegistry $triggers,
        protected ActionRegistry $actions,
        protected ConditionEvaluator $conditionEvaluator
    ) {}

    /**
     * Enroll a contact in a workflow
     */
    public function enroll(Workflow $workflow, Student $student, array $eventData = []): ?WorkflowEnrollment
    {
        // Check if already enrolled and active
        $existing = WorkflowEnrollment::where('workflow_id', $workflow->id)
            ->where('student_id', $student->id)
            ->where('status', 'active')
            ->first();

        if ($existing && !$workflow->settings['allow_re_entry']) {
            return null;
        }

        // Find the trigger step
        $triggerStep = $workflow->steps()->where('type', 'trigger')->first();
        if (!$triggerStep) {
            return null;
        }

        // Create enrollment
        $enrollment = WorkflowEnrollment::create([
            'workflow_id' => $workflow->id,
            'student_id' => $student->id,
            'current_step_id' => $triggerStep->id,
            'status' => 'active',
            'metadata' => [
                'event_data' => $eventData,
                'entry_source' => 'trigger',
            ],
        ]);

        // Queue first step execution
        $this->queueNextSteps($enrollment, $triggerStep);

        return $enrollment;
    }

    /**
     * Process a step execution
     */
    public function processStep(WorkflowStepExecution $execution): void
    {
        $execution->update([
            'status' => 'processing',
            'started_at' => now(),
            'attempts' => $execution->attempts + 1,
        ]);

        try {
            $step = $execution->step;
            $enrollment = $execution->enrollment;
            $student = $enrollment->student;

            $context = new ActionContext(
                student: $student,
                workflowId: $enrollment->workflow_id,
                enrollmentId: $enrollment->id,
                stepExecutionId: $execution->id,
                eventData: $enrollment->metadata['event_data'] ?? [],
                config: $step->config ?? []
            );

            $result = match($step->type) {
                'trigger' => $this->processTrigger($step, $context),
                'action' => $this->processAction($step, $context),
                'condition' => $this->processCondition($step, $context),
                'delay' => $this->processDelay($step, $context, $execution),
                'exit' => $this->processExit($enrollment),
                default => ActionResult::failed('Unknown step type'),
            };

            // Update execution status
            $execution->update([
                'status' => $result->status,
                'completed_at' => now(),
                'result' => $result->toArray(),
            ]);

            // Update enrollment current step
            $enrollment->update(['current_step_id' => $step->id]);

            // Queue next steps if successful
            if ($result->isSuccess()) {
                $this->queueNextSteps($enrollment, $step, $result->getBranch());
            }

        } catch (\Exception $e) {
            $execution->update([
                'status' => 'failed',
                'result' => [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ],
            ]);

            // Retry logic
            if ($execution->attempts < 3) {
                dispatch(new ProcessWorkflowStep($execution->id))
                    ->delay(now()->addMinutes(5 * $execution->attempts));
            }
        }
    }

    protected function processAction($step, ActionContext $context): ActionResult
    {
        $actionHandler = $this->actions->get($step->action_type);

        if (!$actionHandler) {
            return ActionResult::failed("Unknown action type: {$step->action_type}");
        }

        return app($actionHandler)->execute($context);
    }

    protected function processCondition($step, ActionContext $context): ActionResult
    {
        $conditions = $step->config['conditions'] ?? [];

        $result = $this->conditionEvaluator->evaluate(
            $context->getStudent(),
            $conditions,
            $context->getEventData()
        );

        return ActionResult::success([
            'branch' => $result ? 'yes' : 'no',
        ])->withBranch($result ? 'yes' : 'no');
    }

    protected function processDelay($step, ActionContext $context, WorkflowStepExecution $execution): ActionResult
    {
        $config = $step->config;

        $delayUntil = match($config['type'] ?? 'duration') {
            'duration' => now()->add($config['value'], $config['unit']),
            'until_date' => Carbon::parse($config['date']),
            'until_time' => $this->calculateNextTime($config['time'], $config['timezone'] ?? 'UTC'),
            default => now(),
        };

        // If delay hasn't passed yet, reschedule
        if ($delayUntil->isFuture()) {
            $execution->update(['scheduled_at' => $delayUntil]);

            dispatch(new ProcessWorkflowStep($execution->id))
                ->delay($delayUntil);

            return ActionResult::pending(['delayed_until' => $delayUntil]);
        }

        return ActionResult::success(['delay_completed' => true]);
    }

    protected function queueNextSteps(WorkflowEnrollment $enrollment, $currentStep, ?string $branch = null): void
    {
        $connections = $currentStep->outgoingConnections();

        if ($branch) {
            $connections = $connections->where('source_handle', $branch);
        }

        foreach ($connections->get() as $connection) {
            $nextStep = $connection->targetStep;

            // Create execution record
            $execution = WorkflowStepExecution::create([
                'enrollment_id' => $enrollment->id,
                'step_id' => $nextStep->id,
                'status' => 'pending',
                'scheduled_at' => now(),
            ]);

            // Queue processing job
            dispatch(new ProcessWorkflowStep($execution->id));
        }
    }

    protected function processExit(WorkflowEnrollment $enrollment): ActionResult
    {
        $enrollment->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        return ActionResult::success(['workflow_completed' => true]);
    }
}
```

---

## Part 8: API Endpoints

```php
// routes/api.php

Route::middleware(['auth:sanctum', 'verified'])->prefix('v1')->group(function () {

    // Workflows
    Route::prefix('workflows')->group(function () {
        Route::get('/', [WorkflowController::class, 'index']);
        Route::post('/', [WorkflowController::class, 'store']);
        Route::get('/{workflow}', [WorkflowController::class, 'show']);
        Route::put('/{workflow}', [WorkflowController::class, 'update']);
        Route::delete('/{workflow}', [WorkflowController::class, 'destroy']);

        Route::post('/{workflow}/publish', [WorkflowController::class, 'publish']);
        Route::post('/{workflow}/pause', [WorkflowController::class, 'pause']);
        Route::post('/{workflow}/duplicate', [WorkflowController::class, 'duplicate']);

        // Workflow stats
        Route::get('/{workflow}/stats', [WorkflowStatsController::class, 'show']);
        Route::get('/{workflow}/enrollments', [WorkflowEnrollmentController::class, 'index']);
    });

    // Tags
    Route::apiResource('tags', TagController::class);
    Route::post('contacts/{student}/tags', [ContactTagController::class, 'store']);
    Route::delete('contacts/{student}/tags/{tag}', [ContactTagController::class, 'destroy']);

    // Templates
    Route::apiResource('templates', MessageTemplateController::class);
    Route::post('templates/{template}/preview', [MessageTemplateController::class, 'preview']);
    Route::post('templates/{template}/test', [MessageTemplateController::class, 'sendTest']);

    // Lead Scoring
    Route::apiResource('scoring-rules', LeadScoringRuleController::class);
    Route::get('contacts/{student}/score', [ContactScoreController::class, 'show']);
    Route::get('contacts/{student}/score/history', [ContactScoreController::class, 'history']);

    // Segments
    Route::apiResource('segments', SegmentController::class);
    Route::get('segments/{segment}/contacts', [SegmentController::class, 'contacts']);
    Route::post('segments/{segment}/calculate', [SegmentController::class, 'calculate']);

    // Communication
    Route::get('contacts/{student}/communications', [CommunicationLogController::class, 'index']);
    Route::get('contacts/{student}/activities', [ContactActivityController::class, 'index']);

    // Automation metadata
    Route::get('automation/triggers', [AutomationMetaController::class, 'triggers']);
    Route::get('automation/actions', [AutomationMetaController::class, 'actions']);
    Route::get('automation/conditions', [AutomationMetaController::class, 'conditions']);
});
```

---

## Part 9: Integration with Existing OnSend WhatsApp

```php
// app/Services/Workflow/Integrations/OnSendIntegration.php

namespace App\Services\Workflow\Integrations;

use App\Services\WhatsAppService;
use App\Models\CommunicationLog;

class OnSendIntegration implements WhatsAppProviderInterface
{
    public function __construct(
        protected WhatsAppService $whatsAppService
    ) {}

    public function send(
        string $phone,
        string $message,
        array $metadata = []
    ): SendResult {
        // Use existing WhatsApp service with anti-ban features
        $result = $this->whatsAppService->sendMessage(
            phone: $phone,
            message: $message,
            options: [
                'typing_delay' => $metadata['typing_delay'] ?? true,
                'random_delay' => $metadata['random_delay'] ?? 3,
            ]
        );

        return new SendResult(
            success: $result['success'],
            externalId: $result['message_id'] ?? null,
            error: $result['error'] ?? null
        );
    }

    public function getDeliveryStatus(string $messageId): string
    {
        // Check with OnSend API for delivery status
        return $this->whatsAppService->getMessageStatus($messageId);
    }

    public function handleWebhook(array $payload): WebhookResult
    {
        // Process incoming OnSend webhooks
        // - Delivery receipts
        // - Read receipts
        // - Incoming messages (for reply triggers)

        $type = $payload['type'] ?? 'unknown';

        return match($type) {
            'delivery' => $this->handleDeliveryReceipt($payload),
            'read' => $this->handleReadReceipt($payload),
            'message' => $this->handleIncomingMessage($payload),
            default => WebhookResult::ignored(),
        };
    }

    protected function handleIncomingMessage(array $payload): WebhookResult
    {
        // Find student by phone
        $phone = $payload['from'];
        $message = $payload['body'];

        $student = Student::where('phone', $phone)
            ->orWhereHas('user', fn($q) => $q->where('phone', $phone))
            ->first();

        if ($student) {
            // Log incoming message
            CommunicationLog::create([
                'student_id' => $student->id,
                'channel' => 'whatsapp',
                'direction' => 'inbound',
                'recipient' => $phone,
                'content' => $message,
                'external_id' => $payload['id'],
                'status' => 'delivered',
                'delivered_at' => now(),
            ]);

            // Trigger whatsapp_replied event
            event(new WhatsAppReplied($student, $message, $payload));
        }

        return WebhookResult::processed();
    }
}
```

---

## Part 10: Implementation Phases

### Phase 1: Foundation (Weeks 1-2)
- [ ] Database migrations for all tables
- [ ] Base models with relationships
- [ ] Tag system (CRUD, assignment, filtering)
- [ ] Contact activity logging service
- [ ] Basic API endpoints for tags and activities

### Phase 2: React Flow Canvas (Weeks 3-4)
- [ ] Install React Flow and Zustand
- [ ] Create custom node components (Trigger, Action, Condition, Delay)
- [ ] Implement Zustand store for workflow state
- [ ] Build drag-and-drop node palette
- [ ] Implement canvas save/load functionality
- [ ] Add undo/redo support

### Phase 3: Trigger System (Week 5)
- [ ] Trigger registry and interfaces
- [ ] Implement all trigger handlers
- [ ] Event listeners for Laravel events
- [ ] Trigger configuration UI panels

### Phase 4: Action System (Weeks 6-7)
- [ ] Action registry and interfaces
- [ ] Communication actions (Email, WhatsApp via OnSend, SMS)
- [ ] Contact management actions (Tags, Fields, Score)
- [ ] Flow control actions
- [ ] Action configuration UI panels
- [ ] Message template builder with merge tags

### Phase 5: Execution Engine (Week 8)
- [ ] Workflow enrollment logic
- [ ] Step processing with queues
- [ ] Condition evaluator
- [ ] Delay scheduling
- [ ] Error handling and retries
- [ ] Execution logging

### Phase 6: Lead Scoring (Week 9)
- [ ] Scoring rules CRUD
- [ ] Score calculation engine
- [ ] Score decay scheduler
- [ ] Grade assignment logic
- [ ] Score-based triggers and conditions

### Phase 7: Analytics & Polish (Week 10)
- [ ] Workflow performance dashboard
- [ ] Enrollment statistics
- [ ] Communication delivery stats
- [ ] Export functionality
- [ ] UI polish and testing

---

## Part 11: File Structure

```
app/
├── Events/
│   ├── ContactCreated.php
│   ├── OrderPaid.php
│   ├── TagAdded.php
│   ├── ScoreChanged.php
│   └── WhatsAppReplied.php
├── Http/
│   └── Controllers/
│       └── Api/
│           ├── WorkflowController.php
│           ├── TagController.php
│           ├── MessageTemplateController.php
│           ├── LeadScoringRuleController.php
│           ├── SegmentController.php
│           └── AutomationMetaController.php
├── Jobs/
│   ├── ProcessWorkflowStep.php
│   └── RecalculateLeadScores.php
├── Listeners/
│   └── WorkflowTriggerListener.php
├── Models/
│   ├── Workflow.php
│   ├── WorkflowStep.php
│   ├── WorkflowConnection.php
│   ├── WorkflowEnrollment.php
│   ├── WorkflowStepExecution.php
│   ├── Tag.php
│   ├── StudentTag.php
│   ├── LeadScoringRule.php
│   ├── StudentLeadScore.php
│   ├── LeadScoreHistory.php
│   ├── MessageTemplate.php
│   ├── Segment.php
│   ├── CustomField.php
│   ├── StudentCustomField.php
│   ├── ContactActivity.php
│   └── CommunicationLog.php
└── Services/
    └── Workflow/
        ├── WorkflowEngine.php
        ├── TriggerRegistry.php
        ├── ActionRegistry.php
        ├── ConditionEvaluator.php
        ├── ActionContext.php
        ├── ActionResult.php
        ├── Triggers/
        │   ├── TriggerInterface.php
        │   ├── ContactCreatedTrigger.php
        │   ├── OrderPaidTrigger.php
        │   └── ...
        ├── Actions/
        │   ├── ActionInterface.php
        │   ├── SendWhatsAppAction.php
        │   ├── SendEmailAction.php
        │   ├── AddTagAction.php
        │   ├── UpdateScoreAction.php
        │   └── ...
        └── Integrations/
            ├── WhatsAppProviderInterface.php
            └── OnSendIntegration.php

resources/
└── js/
    ├── components/
    │   └── workflow/
    │       ├── WorkflowBuilder.tsx
    │       ├── Toolbar.tsx
    │       ├── nodes/
    │       │   ├── TriggerNode.tsx
    │       │   ├── ActionNode.tsx
    │       │   ├── ConditionNode.tsx
    │       │   └── DelayNode.tsx
    │       └── panels/
    │           ├── NodePanel.tsx
    │           ├── ConfigPanel.tsx
    │           ├── ConditionBuilder.tsx
    │           └── TemplateSelector.tsx
    ├── stores/
    │   └── workflowStore.ts
    └── types/
        └── workflow.ts

database/
└── migrations/
    ├── 2025_01_24_000001_create_workflows_table.php
    ├── 2025_01_24_000002_create_workflow_steps_table.php
    ├── 2025_01_24_000003_create_workflow_connections_table.php
    ├── 2025_01_24_000004_create_workflow_enrollments_table.php
    ├── 2025_01_24_000005_create_workflow_step_executions_table.php
    ├── 2025_01_24_000006_create_tags_table.php
    ├── 2025_01_24_000007_create_student_tags_table.php
    ├── 2025_01_24_000008_create_lead_scoring_rules_table.php
    ├── 2025_01_24_000009_create_student_lead_scores_table.php
    ├── 2025_01_24_000010_create_lead_score_history_table.php
    ├── 2025_01_24_000011_create_message_templates_table.php
    ├── 2025_01_24_000012_create_segments_table.php
    ├── 2025_01_24_000013_create_custom_fields_table.php
    ├── 2025_01_24_000014_create_student_custom_fields_table.php
    ├── 2025_01_24_000015_create_contact_activities_table.php
    └── 2025_01_24_000016_create_communication_logs_table.php
```

---

## Part 12: Key Considerations

### Performance
- Use database indexes on frequently queried columns
- Implement eager loading for workflow relationships
- Cache workflow definitions (Redis)
- Use queues for all step executions
- Batch process enrollments during high volume

### Security
- Validate all API inputs
- Sanitize message template variables
- Rate limit API endpoints
- Audit log all workflow modifications
- Encrypt sensitive template data

### Scalability
- Horizontal scaling with queue workers
- Partition large tables by date
- Archive old enrollments and executions
- Use database read replicas for analytics

### Monitoring
- Log all workflow executions
- Track action success/failure rates
- Monitor queue depth and processing time
- Alert on high failure rates
- Dashboard for real-time workflow health

---

## Part 13: Dependencies to Install

```json
// package.json additions
{
  "dependencies": {
    "reactflow": "^11.10.0",
    "zustand": "^4.5.0",
    "@headlessui/react": "^1.7.0",
    "@heroicons/react": "^2.1.0"
  }
}
```

```bash
# Install React Flow and state management
npm install reactflow zustand @headlessui/react @heroicons/react
```

---

This comprehensive plan provides the foundation for building an industry-standard visual workflow/automation builder integrated with your existing Laravel application and OnSend WhatsApp service. The React Flow-based canvas allows for intuitive drag-and-drop workflow creation with full support for triggers, actions, conditions, delays, and branching logic.
