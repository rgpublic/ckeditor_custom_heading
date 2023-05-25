<?php
namespace Drupal\ckeditor_custom_heading\Plugin\CKEditor5Plugin;

use Drupal\ckeditor5\Plugin\CKEditor5Plugin\Heading as HeadingBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\editor\EditorInterface;

class Heading extends HeadingBase {

    public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
        $form=parent::buildConfigurationForm($form, $form_state);

        $customize = $this->configuration['customize'] ?? false;

        $form['customize'] = [
            '#type' => 'checkbox',
            '#title' => 'Customize headings',
            '#default_value' => $customize,
            '#weight' => -50
        ];

        $form['custom_headings_container'] = [
            '#type' => 'fieldset',
            '#title' => $form['enabled_headings']['#title'],
            '#description' => $form['enabled_headings']['#description'],
            'custom_headings' => [
                '#type' => 'textarea',
                '#default_value' => $this->configuration['custom_headings']??''
            ]
        ];

        $form['enabled_headings']['#access'] = !$customize;
        $form['custom_headings_container']['#access'] = $customize;

        return $form;
    }

    private function getModelFromItem($item) {
        $tag=$item['tag']??'';
        if ($tag == 'p') {
            $name = 'paragraph';
        } elseif (preg_match('/^h([1-6])$/', $tag, $matches)) {
            $name = 'heading' . $matches[1];
        } else {
            throw new \Exception('Unknown tag "' . $tag . '"');
        }
        $class = $item['class'] ?? '';
        if ($class) {
            $name .= ucfirst($class);
        }
        return $name;
    }

    private function camel2snake($camel) {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $camel));
    }

    public function getDynamicPluginConfig(array $static_plugin_config, EditorInterface $editor): array {
        $customize = $this->configuration['customize'] ?? false;
        if (!$customize) {
            return parent::getDynamicPluginConfig($static_plugin_config, $editor);
        }

        $custom_headings = $this->configuration['custom_headings'] ?? '';

        $items = $this->parseConfiguration($custom_headings);

        $configured_heading_options = [];
        foreach ($items as $item) {
             $entry = [];
             $model = $this->getModelFromItem($item);
             $baseModel = $this->getModelFromItem(['tag' => $item['tag']]);
             $entry['model'] = $model;
             if ($item['tag'] != 'p') $entry['view'] = $item['tag'];
             $entry['title'] = $item['title'];

             $class = $item['class'] ?? '';
             $classes = [];
             if ($class) $classes[] = 'ck-heading_'.$baseModel;
             $classes[] = 'ck-heading_' . $this->camel2snake($model);

             $entry['class'] = implode(' ', $classes);
             if ($class) {
                $entry['view']=['name' => $item['tag'], 'classes' => $class];
                $entry['converterPriority'] = 'high';
             }

             $configured_heading_options[] = $entry;
        }

        return [
          'heading' => [
            'options' => array_values($configured_heading_options),
          ],
        ];
    }

    public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
        parent::submitConfigurationForm($form, $form_state);
        $this->configuration['customize'] = $form_state->getValue('customize');
        $this->configuration['custom_headings'] = $form_state->getValue([
            'custom_headings_container',
            'custom_headings']
        );
    }

    private function parseConfiguration($enabled_headings): array {
        $result = [];
        $lines = explode("\n", $enabled_headings);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') continue;
            $regex_tag = '(?<tag>[^\|\.]*)';
            $regex_class = '(?:\.(?<class>[^|]*))?';
            $regex_title = '(?:\|(?<title>.*))?';
            if (!preg_match("/^{$regex_tag}{$regex_class}{$regex_title}$/", $line, $matches)) {
                continue;
            }
            $matches = array_filter($matches, "is_string", ARRAY_FILTER_USE_KEY);
            $result[] = $matches;
        };
        return $result;
    }

    public function getElementsSubset(): array {
        $plugin_definition = $this->getPluginDefinition();
        $enabled_headings = $this->configuration['enabled_headings'];
        if (is_array($enabled_headings)) return parent::getElementsSubset();

        $elements = $plugin_definition->getElements();
        $items = $this->parseConfiguration($enabled_headings);
        $tags_to_return = [];
        foreach ($items as $item) {
            $tag = '<'.$item['tag'].'>';
            if (!in_array($tag,$elements)) continue;
            $tags_to_return[] = $tag;
        }
        return array_values(array_unique($tags_to_return));
    }

}
