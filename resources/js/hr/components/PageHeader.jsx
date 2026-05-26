// Backwards-compat default export — forwards to the v2 PageHeader.
// Maps singular `action` prop to v2's `actions`. New code should
// `import { PageHeader } from './ui/page-header'` for breadcrumb/eyebrow support.
import { PageHeader as PageHeaderV2 } from './ui/page-header';

export default function PageHeader({ title, description, action, ...rest }) {
    return <PageHeaderV2 title={title} description={description} actions={action} {...rest} />;
}
