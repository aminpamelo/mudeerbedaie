import TriggerNode from './TriggerNode';
import ActionNode from './ActionNode';
import ConditionNode from './ConditionNode';
import DelayNode from './DelayNode';

export const nodeTypes = {
    trigger: TriggerNode,
    action: ActionNode,
    condition: ConditionNode,
    delay: DelayNode,
};

export { TriggerNode, ActionNode, ConditionNode, DelayNode };
